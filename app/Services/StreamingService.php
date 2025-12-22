<?php

namespace App\Services;

use App\Models\Stream;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class StreamingService
{
    private string $rtmpUrl;
    private string $hlsUrl;
    private string $nginxControlUrl;

    public function __construct()
    {
        $this->rtmpUrl = config('streaming.rtmp_url', 'rtmp://localhost:1935/live');
        $this->hlsUrl = config('streaming.hls_url', 'http://localhost:8080/hls');
        $this->nginxControlUrl = config('streaming.nginx_control_url', 'http://localhost:8080/control');
    }

    public function createStream(User $user, array $data): Stream
    {
        $streamKey = $this->generateStreamKey();

        $stream = Stream::create([
            'user_id' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'stream_key' => $streamKey,
            'status' => 'created',
            'is_private' => $data['is_private'] ?? false,
            'category' => $data['category'] ?? 'general',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'settings' => [
                'allow_chat' => $data['allow_chat'] ?? true,
                'record_stream' => $data['record_stream'] ?? true,
                'quality_options' => ['480p', '720p', '1080p'],
            ],
        ]);

        // Store stream info in Redis for quick access
        Redis::hset("stream:{$streamKey}", [
            'id' => $stream->id,
            'user_id' => $user->id,
            'title' => $stream->title,
            'status' => 'created',
            'viewers' => 0,
            'created_at' => now()->toISOString(),
        ]);

        Log::info('Stream created', ['stream_id' => $stream->id, 'user_id' => $user->id]);

        return $stream;
    }

    public function startStream(string $streamKey): bool
    {
        $stream = Stream::where('stream_key', $streamKey)->first();

        if (! $stream) {
            return false;
        }

        $stream->update([
            'status' => 'live',
            'started_at' => now(),
        ]);

        // Update Redis
        Redis::hset("stream:{$streamKey}", [
            'status' => 'live',
            'started_at' => now()->toISOString(),
        ]);

        // Broadcast stream started event
        if (! app()->environment('testing')) {
            broadcast(new \App\Events\StreamStarted($stream));
        }

        // Notify followers
        $this->notifyFollowers($stream);

        Log::info('Stream started', ['stream_id' => $stream->id]);

        return true;
    }

    public function endStream(string $streamKey): bool
    {
        $stream = Stream::where('stream_key', $streamKey)->first();

        if (! $stream) {
            return false;
        }

        $duration = 0;
        if ($stream->started_at) {
            $duration = max(0, now()->diffInSeconds($stream->started_at));
        }

        $stream->update([
            'status' => 'ended',
            'ended_at' => now(),
            'duration' => $duration,
            'peak_viewers' => Redis::hget("stream:{$streamKey}", 'peak_viewers') ?? 0,
        ]);

        // Update Redis
        Redis::hset("stream:{$streamKey}", [
            'status' => 'ended',
            'ended_at' => now()->toISOString(),
            'duration' => $duration,
        ]);

        // Broadcast stream ended event
        if (! app()->environment('testing')) {
            broadcast(new \App\Events\StreamEnded($stream));
        }

        // Process recording if enabled
        if ($stream->settings['record_stream'] ?? false) {
            $this->processRecording($stream);
        }

        Log::info('Stream ended', ['stream_id' => $stream->id, 'duration' => $duration]);

        return true;
    }

    public function joinStream(string $streamKey, ?User $user = null): array
    {
        $streamData = Redis::hgetall("stream:{$streamKey}");

        if (empty($streamData) || $streamData['status'] !== 'live') {
            return ['success' => false, 'message' => 'Stream not found or not live'];
        }

        // Increment viewer count
        $viewers = Redis::hincrby("stream:{$streamKey}", 'viewers', 1);

        // Update peak viewers
        $peakViewers = Redis::hget("stream:{$streamKey}", 'peak_viewers') ?? 0;
        if ($viewers > $peakViewers) {
            Redis::hset("stream:{$streamKey}", 'peak_viewers', $viewers);
        }

        // Add user to viewers list
        if ($user) {
            Redis::sadd("stream_viewers:{$streamKey}", $user->id);
            Redis::expire("stream_viewers:{$streamKey}", 3600);
        }

        // Get stream URLs
        $urls = $this->getStreamUrls($streamKey);

        return [
            'success' => true,
            'stream' => [
                'id' => $streamData['id'],
                'title' => $streamData['title'],
                'viewers' => $viewers,
                'urls' => $urls,
            ],
        ];
    }

    public function leaveStream(string $streamKey, ?User $user = null): bool
    {
        // Decrement viewer count
        Redis::hincrby("stream:{$streamKey}", 'viewers', -1);

        // Remove user from viewers list
        if ($user) {
            Redis::srem("stream_viewers:{$streamKey}", $user->id);
        }

        return true;
    }

    public function getStreamUrls(string $streamKey): array
    {
        return [
            'hls' => [
                'master' => "{$this->hlsUrl}/{$streamKey}/index.m3u8",
                'qualities' => [
                    '480p' => "{$this->hlsUrl}/{$streamKey}_low/index.m3u8",
                    '720p' => "{$this->hlsUrl}/{$streamKey}_mid/index.m3u8",
                    '1080p' => "{$this->hlsUrl}/{$streamKey}_high/index.m3u8",
                    'source' => "{$this->hlsUrl}/{$streamKey}_src/index.m3u8",
                ],
            ],
            'rtmp' => [
                'publish' => "{$this->rtmpUrl}/{$streamKey}",
                'play' => "{$this->rtmpUrl}/{$streamKey}",
            ],
        ];
    }

    public function getStreamStats(string $streamKey): array
    {
        $streamData = Redis::hgetall("stream:{$streamKey}");
        $viewers = Redis::smembers("stream_viewers:{$streamKey}");

        return [
            'viewers' => (int) ($streamData['viewers'] ?? 0),
            'peak_viewers' => (int) ($streamData['peak_viewers'] ?? 0),
            'duration' => $this->calculateDuration($streamData['started_at'] ?? null),
            'status' => $streamData['status'] ?? 'unknown',
            'viewer_list' => $viewers,
        ];
    }

    public function getLiveStreams(int $limit = 20): array
    {
        $liveStreams = Stream::where('status', 'live')
            ->with('user')
            ->orderBy('started_at', 'desc')
            ->limit($limit)
            ->get();

        return $liveStreams->map(function ($stream) {
            $stats = $this->getStreamStats($stream->stream_key);

            return [
                'id' => $stream->id,
                'title' => $stream->title,
                'description' => $stream->description,
                'user' => $stream->user->only(['id', 'name', 'username', 'avatar']),
                'viewers' => $stats['viewers'],
                'duration' => $stats['duration'],
                'thumbnail' => $this->getStreamThumbnail($stream->stream_key),
                'urls' => $this->getStreamUrls($stream->stream_key),
            ];
        })->toArray();
    }

    public function authenticateStream(string $streamKey): bool
    {
        $stream = Stream::where('stream_key', $streamKey)->first();

        if (! $stream) {
            return false;
        }

        if ($stream->status === 'ended') {
            return false;
        }

        // Additional authentication logic can be added here
        return true;
    }

    public function generateStreamThumbnail(string $streamKey): ?string
    {
        $thumbnailPath = storage_path("app/public/thumbnails/{$streamKey}.jpg");
        $hlsPath = storage_path("app/streams/{$streamKey}/index.m3u8");

        if (! file_exists($hlsPath)) {
            return null;
        }

        // Use FFmpeg to generate thumbnail
        $command = "ffmpeg -i {$hlsPath} -ss 00:00:01 -vframes 1 -y {$thumbnailPath}";
        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists($thumbnailPath)) {
            return asset("storage/thumbnails/{$streamKey}.jpg");
        }

        return null;
    }

    private function generateStreamKey(): string
    {
        return 'stream_' . Str::random(32);
    }

    private function notifyFollowers(Stream $stream): void
    {
        $followers = $stream->user->followers;

        foreach ($followers as $follower) {
            $follower->notify(new \App\Notifications\StreamStarted($stream));
        }
    }

    private function processRecording(Stream $stream): void
    {
        $recordingPath = storage_path("app/recordings/{$stream->stream_key}.flv");

        if (file_exists($recordingPath)) {
            // Convert to MP4 for better compatibility
            $mp4Path = storage_path("app/recordings/{$stream->stream_key}.mp4");
            $command = "ffmpeg -i {$recordingPath} -c copy {$mp4Path}";
            exec($command);

            // Update stream with recording info
            $stream->update([
                'recording_path' => "recordings/{$stream->stream_key}.mp4",
                'recording_size' => filesize($mp4Path),
            ]);
        }
    }

    private function calculateDuration(?string $startedAt): int
    {
        if (! $startedAt) {
            return 0;
        }

        try {
            $started = \Carbon\Carbon::parse($startedAt);
            $duration = now()->diffInSeconds($started);

            return max(0, $duration); // Ensure non-negative
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getStreamThumbnail(string $streamKey): ?string
    {
        $thumbnailPath = "thumbnails/{$streamKey}.jpg";

        if (file_exists(storage_path("app/public/{$thumbnailPath}"))) {
            return asset("storage/{$thumbnailPath}");
        }

        return null;
    }
}
