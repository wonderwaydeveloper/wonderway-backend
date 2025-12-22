<?php

namespace App\Http\Controllers\Api;

use App\Events\StreamEnded;
use App\Events\StreamStarted;
use App\Events\StreamViewerJoined;
use App\Events\StreamViewerLeft;
use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LiveStreamController extends Controller
{
    public function index()
    {
        $streams = LiveStream::with('user:id,name,username,avatar')
            ->live()
            ->orderBy('viewer_count', 'desc')
            ->paginate(20);

        return response()->json($streams);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|string|max:100',
            'is_private' => 'boolean',
        ]);

        $stream = LiveStream::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'category' => $request->category,
            'is_private' => $request->boolean('is_private'),
            'stream_key' => Str::random(32),
            'rtmp_url' => 'rtmp://live.wonderway.com/live/' . Str::random(32),
            'hls_url' => 'https://live.wonderway.com/hls/' . Str::random(32) . '.m3u8',
        ]);

        return response()->json($stream->load('user:id,name,username,avatar'), 201);
    }

    public function show(LiveStream $stream)
    {
        return response()->json($stream->load(['user:id,name,username,avatar', 'viewers:id,name,username,avatar']));
    }

    public function start(LiveStream $stream)
    {
        $this->authorize('update', $stream);

        $stream->update([
            'status' => 'live',
            'started_at' => now(),
        ]);

        if (! app()->environment('testing')) {
            broadcast(new StreamStarted($stream));
        }

        return response()->json(['message' => 'Stream started successfully']);
    }

    public function end(LiveStream $stream)
    {
        $this->authorize('update', $stream);

        $endedAt = now();
        $duration = $stream->started_at ? $endedAt->diffInSeconds($stream->started_at) : 0;

        $stream->update([
            'status' => 'ended',
            'ended_at' => $endedAt,
            'duration' => max(0, $duration), // Ensure duration is never negative
        ]);

        if (! app()->environment('testing')) {
            broadcast(new StreamEnded($stream));
        }

        return response()->json(['message' => 'Stream ended successfully']);
    }

    public function join(LiveStream $stream)
    {
        if ($stream->is_private && ! $stream->user->isFollowing(auth()->id())) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $stream->viewers()->syncWithoutDetaching([auth()->id()]);
        $stream->increment('viewer_count');

        if ($stream->viewer_count > $stream->max_viewers) {
            $stream->update(['max_viewers' => $stream->viewer_count]);
        }

        // Only broadcast in non-testing environment
        if (! app()->environment('testing')) {
            broadcast(new StreamViewerJoined($stream, auth()->user()));
        }

        return response()->json(['message' => 'Joined stream successfully']);
    }

    public function leave(LiveStream $stream)
    {
        $stream->viewers()->detach(auth()->id());
        $stream->decrement('viewer_count');

        // Only broadcast in non-testing environment
        if (! app()->environment('testing')) {
            broadcast(new StreamViewerLeft($stream, auth()->user()));
        }

        return response()->json(['message' => 'Left stream successfully']);
    }
}
