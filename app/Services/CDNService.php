<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CDNService
{
    private array $cdnEndpoints = [
        'images' => 'https://cdn-images.wonderway.com',
        'videos' => 'https://cdn-videos.wonderway.com',
        'static' => 'https://cdn-static.wonderway.com',
    ];

    public function uploadImage(UploadedFile $file, string $folder = 'posts'): array
    {
        $filename = $this->generateFilename($file);
        $path = "{$folder}/{$filename}";
        
        // Upload to multiple locations for redundancy
        $uploaded = Storage::disk('s3')->put($path, $file->getContent(), 'public');
        
        if ($uploaded) {
            // Trigger CDN cache warming
            $this->warmCache($path, 'images');
            
            return [
                'path' => $path,
                'url' => $this->getCDNUrl($path, 'images'),
                'thumbnail' => $this->generateThumbnail($path),
            ];
        }
        
        throw new \Exception('Failed to upload to CDN');
    }

    public function uploadVideo(UploadedFile $file, string $folder = 'videos'): array
    {
        $filename = $this->generateFilename($file);
        $path = "{$folder}/{$filename}";
        
        $uploaded = Storage::disk('s3')->put($path, $file->getContent(), 'public');
        
        if ($uploaded) {
            // Create video record first, then queue processing
            $video = \App\Models\Video::create([
                'post_id' => null, // Will be set later if needed
                'original_path' => $path,
                'file_size' => $file->getSize(),
                'encoding_status' => 'pending'
            ]);
            
            // Queue video processing with Video model
            dispatch(new \App\Jobs\ProcessVideoJob($video));
            
            return [
                'path' => $path,
                'url' => $this->getCDNUrl($path, 'videos'),
                'processing' => true,
                'video_id' => $video->id,
            ];
        }
        
        throw new \Exception('Failed to upload video to CDN');
    }

    public function getCDNUrl(string $path, string $type = 'images'): string
    {
        $baseUrl = $this->cdnEndpoints[$type] ?? $this->cdnEndpoints['static'];
        return "{$baseUrl}/{$path}";
    }

    private function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $hash = hash('sha256', $file->getContent() . microtime(true) . rand());
        return date('Y/m/d') . '/' . substr($hash, 0, 16) . '.' . $extension;
    }

    private function warmCache(string $path, string $type): void
    {
        $url = $this->getCDNUrl($path, $type);
        
        // Warm cache in background
        dispatch(function () use ($url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($ch);
            curl_close($ch);
        })->onQueue('cdn');
    }

    private function generateThumbnail(string $path): string
    {
        $thumbnailPath = str_replace('.', '_thumb.', $path);
        
        // Queue thumbnail generation
        dispatch(new \App\Jobs\GenerateThumbnailJob($path, $thumbnailPath));
        
        return $this->getCDNUrl($thumbnailPath, 'images');
    }

    public function invalidateCache(string $path): bool
    {
        // Implement CDN cache invalidation
        // This would typically call your CDN provider's API
        return true;
    }
}