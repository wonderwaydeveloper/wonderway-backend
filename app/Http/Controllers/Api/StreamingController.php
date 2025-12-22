<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stream;
use App\Services\StreamingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StreamingController extends Controller
{
    private StreamingService $streamingService;

    public function __construct(StreamingService $streamingService)
    {
        $this->streamingService = $streamingService;
    }

    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|string|in:gaming,music,talk,education,entertainment,sports,technology,other',
            'is_private' => 'boolean',
            'scheduled_at' => 'nullable|date|after:now',
            'allow_chat' => 'boolean',
            'record_stream' => 'boolean',
        ]);

        $stream = $this->streamingService->createStream(auth()->user(), $request->all());

        return response()->json([
            'success' => true,
            'stream' => [
                'id' => $stream->id,
                'title' => $stream->title,
                'stream_key' => $stream->stream_key,
                'rtmp_url' => config('streaming.rtmp_url') . '/' . $stream->stream_key,
                'status' => $stream->status,
            ],
        ], 201);
    }

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'stream_key' => 'required|string',
        ]);

        $success = $this->streamingService->startStream($request->stream_key);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Stream not found or cannot be started',
            ], 404);
        }

        return response()->json(['success' => true]);
    }

    public function end(Request $request): JsonResponse
    {
        $request->validate([
            'stream_key' => 'required|string',
        ]);

        $success = $this->streamingService->endStream($request->stream_key);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Stream not found',
            ], 404);
        }

        return response()->json(['success' => true]);
    }

    public function join(Request $request, string $streamKey): JsonResponse
    {
        $result = $this->streamingService->joinStream($streamKey, auth()->user());

        if (! $result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    public function leave(Request $request, string $streamKey): JsonResponse
    {
        $this->streamingService->leaveStream($streamKey, auth()->user());

        return response()->json(['success' => true]);
    }

    public function stats(string $streamKey): JsonResponse
    {
        $stats = $this->streamingService->getStreamStats($streamKey);

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    public function live(): JsonResponse
    {
        $streams = $this->streamingService->getLiveStreams();

        return response()->json([
            'success' => true,
            'streams' => $streams,
        ]);
    }

    public function show(Stream $stream): JsonResponse
    {
        $stream->load('user:id,name,username,avatar');

        return response()->json([
            'success' => true,
            'stream' => [
                'id' => $stream->id,
                'title' => $stream->title,
                'description' => $stream->description,
                'status' => $stream->status,
                'category' => $stream->category,
                'is_private' => $stream->is_private,
                'user' => $stream->user,
                'viewers' => $stream->is_live ? $this->streamingService->getStreamStats($stream->stream_key)['viewers'] : 0,
                'duration' => $stream->duration_formatted,
                'thumbnail' => $stream->thumbnail,
                'urls' => $stream->is_live ? $stream->stream_urls : null,
                'created_at' => $stream->created_at,
                'started_at' => $stream->started_at,
                'ended_at' => $stream->ended_at,
            ],
        ]);
    }

    public function myStreams(): JsonResponse
    {
        $streams = Stream::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'streams' => $streams,
        ]);
    }

    public function delete(Stream $stream): JsonResponse
    {
        if ($stream->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($stream->status === 'live') {
            $this->streamingService->endStream($stream->stream_key);
        }

        $stream->delete();

        return response()->json(['success' => true]);
    }

    // Webhook endpoints for Nginx RTMP
    public function auth(Request $request): JsonResponse
    {
        $streamKey = $request->input('name');

        if (! $streamKey) {
            return response()->json(['success' => false], 403);
        }

        $authenticated = $this->streamingService->authenticateStream($streamKey);

        return response()->json(['success' => $authenticated], $authenticated ? 200 : 403);
    }

    public function publishDone(Request $request): JsonResponse
    {
        $streamKey = $request->input('name');

        if ($streamKey) {
            $this->streamingService->endStream($streamKey);
        }

        return response()->json(['success' => true]);
    }

    public function play(Request $request): JsonResponse
    {
        $streamKey = $request->input('name');

        if ($streamKey) {
            $this->streamingService->joinStream($streamKey);
        }

        return response()->json(['success' => true]);
    }

    public function playDone(Request $request): JsonResponse
    {
        $streamKey = $request->input('name');

        if ($streamKey) {
            $this->streamingService->leaveStream($streamKey);
        }

        return response()->json(['success' => true]);
    }
}
