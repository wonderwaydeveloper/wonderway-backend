<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\CDNService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class GenerateThumbnailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    protected $imagePath;
    protected $type;
    protected $post;

    public function __construct($imagePath = null, string $type = 'post', Post $post = null)
    {
        $this->imagePath = $imagePath;
        $this->type = $type;
        $this->post = $post;
        $this->onQueue('image-processing');
    }

    public function handle(CDNService $cdnService = null): void
    {
        // Legacy support for existing posts
        if ($this->post && ! $this->imagePath) {
            $this->handleLegacyPost($cdnService);

            return;
        }

        // New thumbnail generation
        if ($this->imagePath) {
            $this->handleNewThumbnail();
        }
    }

    private function handleLegacyPost($cdnService)
    {
        if (! $this->post->image || ! $cdnService) {
            return;
        }

        try {
            $thumbnailUrl = $cdnService->generateThumbnail($this->post->image);

            $this->post->update([
                'thumbnail' => $thumbnailUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('Legacy thumbnail generation failed', [
                'post_id' => $this->post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handleNewThumbnail()
    {
        try {
            if (! Storage::disk('public')->exists($this->imagePath)) {
                Log::warning('Image not found for thumbnail generation', ['path' => $this->imagePath]);

                return;
            }

            $imageContent = Storage::disk('public')->get($this->imagePath);
            $manager = new ImageManager(new Driver());
            $image = $manager->read($imageContent);

            // Generate different thumbnail sizes based on type
            $thumbnails = $this->getThumbnailSizes($this->type);

            foreach ($thumbnails as $size => $dimensions) {
                $manager = new ImageManager(new Driver());
                $thumbnail = $manager->read($imageContent);
                $thumbnail->cover($dimensions['width'], $dimensions['height']);

                $thumbnailPath = $this->getThumbnailPath($this->imagePath, $size);
                $thumbnailContent = $thumbnail->toJpeg(80)->toString();

                Storage::disk('public')->put($thumbnailPath, $thumbnailContent);
            }

            Log::info('Thumbnails generated successfully', [
                'original_path' => $this->imagePath,
                'type' => $this->type,
                'thumbnails_count' => count($thumbnails),
            ]);

        } catch (\Exception $e) {
            Log::error('Thumbnail generation failed', [
                'path' => $this->imagePath,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function getThumbnailSizes(string $type): array
    {
        switch ($type) {
            case 'post':
                return [
                    'small' => ['width' => 300, 'height' => 300],
                    'medium' => ['width' => 600, 'height' => 600],
                ];
            case 'story':
                return [
                    'small' => ['width' => 200, 'height' => 356],
                    'medium' => ['width' => 400, 'height' => 712],
                ];
            default:
                return [
                    'small' => ['width' => 150, 'height' => 150],
                ];
        }
    }

    private function getThumbnailPath(string $originalPath, string $size): string
    {
        $pathInfo = pathinfo($originalPath);
        $directory = str_replace('/media/', '/media/thumbnails/', $pathInfo['dirname']);
        $filename = $pathInfo['filename'] . "_{$size}." . $pathInfo['extension'];

        return $directory . '/' . $filename;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Thumbnail generation job permanently failed', [
            'path' => $this->imagePath,
            'type' => $this->type,
            'post_id' => $this->post?->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
