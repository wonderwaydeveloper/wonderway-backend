<?php

namespace App\Services;

use App\Jobs\ProcessVideoJob;
use App\Models\Post;
use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VideoUploadService
{
    private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB
    private const ALLOWED_FORMATS = ['mp4', 'mov', 'avi', 'mkv', 'webm'];
    private const MAX_DURATION = 300; // 5 minutes

    public function uploadVideo(UploadedFile $file, Post $post): Video
    {
        $this->validateVideo($file);

        // Store original video
        $originalPath = $file->store('videos/original', 'public');
        
        // Get basic file info
        $fileSize = $file->getSize();
        
        // Create video record
        $video = Video::create([
            'post_id' => $post->id,
            'original_path' => $originalPath,
            'file_size' => $fileSize,
            'encoding_status' => 'pending'
        ]);

        // Update post with video path
        $post->update(['video' => $originalPath]);

        // Dispatch processing job
        ProcessVideoJob::dispatch($video)->onQueue('video-processing');

        return $video;
    }

    private function validateVideo(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \Exception('Video file is too large. Maximum size is 100MB.');
        }

        // Check file format
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_FORMATS)) {
            throw new \Exception('Invalid video format. Allowed formats: ' . implode(', ', self::ALLOWED_FORMATS));
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!str_starts_with($mimeType, 'video/')) {
            throw new \Exception('File is not a valid video.');
        }
    }

    public function deleteVideo(Video $video): void
    {
        // Delete original file
        if (Storage::disk('public')->exists($video->original_path)) {
            Storage::disk('public')->delete($video->original_path);
        }

        // Delete processed files
        if ($video->processed_paths) {
            foreach ($video->processed_paths as $path) {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        }

        // Delete thumbnail
        if ($video->thumbnail_path && Storage::disk('public')->exists($video->thumbnail_path)) {
            Storage::disk('public')->delete($video->thumbnail_path);
        }

        $video->delete();
    }
}