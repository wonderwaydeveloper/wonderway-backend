<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class CDNService
{
    private string $cdnUrl;
    private string $disk;

    public function __construct()
    {
        $this->cdnUrl = config('services.cdn.url', config('app.url'));
        $this->disk = config('services.cdn.disk', 's3');
    }

    public function uploadImage(UploadedFile $file, string $directory = 'images'): string
    {
        // Optimize image
        $optimized = $this->optimizeImage($file);

        // Generate unique filename
        $filename = $this->generateFilename($file->getClientOriginalExtension());
        $path = "{$directory}/{$filename}";

        // Upload to storage
        Storage::disk($this->disk)->put($path, $optimized);

        // Return CDN URL
        return $this->getCDNUrl($path);
    }

    public function uploadVideo(UploadedFile $file, string $directory = 'videos'): string
    {
        $filename = $this->generateFilename($file->getClientOriginalExtension());
        $path = "{$directory}/{$filename}";

        Storage::disk($this->disk)->putFileAs($directory, $file, $filename);

        return $this->getCDNUrl($path);
    }

    public function deleteFile(string $path): bool
    {
        try {
            return Storage::disk($this->disk)->delete($path);
        } catch (\Exception $e) {
            \Log::error('CDN file deletion failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getCDNUrl(string $path): string
    {
        if ($this->disk === 's3') {
            return $this->cdnUrl . '/' . $path;
        }

        return Storage::disk($this->disk)->url($path);
    }

    private function optimizeImage(UploadedFile $file): string
    {
        $image = Image::make($file);

        // Resize if too large
        if ($image->width() > 1200 || $image->height() > 1200) {
            $image->resize(1200, 1200, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        // Convert to WebP for better compression
        $image->encode('webp', 85);

        return $image->stream()->getContents();
    }

    private function generateFilename(string $extension): string
    {
        return uniqid() . '_' . time() . '.' . $extension;
    }

    public function generateThumbnail(string $imagePath, int $width = 300, int $height = 300): string
    {
        try {
            $originalImage = Storage::disk($this->disk)->get($imagePath);
            $image = Image::make($originalImage);

            $thumbnail = $image->fit($width, $height)->encode('webp', 80);

            $thumbnailPath = str_replace('.', '_thumb.', $imagePath);
            Storage::disk($this->disk)->put($thumbnailPath, $thumbnail->stream()->getContents());

            return $this->getCDNUrl($thumbnailPath);
        } catch (\Exception $e) {
            \Log::error('Thumbnail generation failed', [
                'path' => $imagePath,
                'error' => $e->getMessage(),
            ]);

            return $this->getCDNUrl($imagePath);
        }
    }
}
