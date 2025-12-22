<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\JsonResponse;

class VideoController extends Controller
{
    public function status(Video $video): JsonResponse
    {
        return response()->json([
            'id' => $video->id,
            'encoding_status' => $video->encoding_status,
            'duration' => $video->duration,
            'thumbnail_url' => $video->thumbnail_path ? asset('storage/' . $video->thumbnail_path) : null,
            'video_urls' => $video->isProcessed() ? [
                '480p' => $video->getUrl('480p') ? asset('storage/' . $video->getUrl('480p')) : null,
                '720p' => $video->getUrl('720p') ? asset('storage/' . $video->getUrl('720p')) : null,
                '1080p' => $video->getUrl('1080p') ? asset('storage/' . $video->getUrl('1080p')) : null,
            ] : null,
        ]);
    }
}