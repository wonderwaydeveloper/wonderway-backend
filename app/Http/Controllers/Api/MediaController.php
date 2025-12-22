<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateThumbnailJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class MediaController extends Controller
{
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // 10MB max
            'type' => 'in:post,avatar,cover,story',
            'quality' => 'integer|min:60|max:100',
        ]);

        try {
            $file = $request->file('image');
            $type = $request->input('type', 'post');
            $quality = $request->input('quality', 85);

            // Generate unique filename
            $filename = $this->generateFilename($file->getClientOriginalExtension());
            $path = "media/{$type}s/" . date('Y/m/d');

            // Process and optimize image
            $processedImage = $this->processImage($file, $type, $quality);

            // Store original and processed versions
            $fullPath = "{$path}/{$filename}";
            Storage::disk('public')->put($fullPath, $processedImage);

            // Generate thumbnail for certain types
            if (in_array($type, ['post', 'story'])) {
                GenerateThumbnailJob::dispatch($fullPath, $type);
            }

            $url = Storage::disk('public')->url($fullPath);

            return response()->json([
                'message' => 'فایل با موفقیت آپلود شد',
                'data' => [
                    'url' => $url,
                    'path' => $fullPath,
                    'filename' => $filename,
                    'size' => strlen($processedImage),
                    'type' => $type,
                    'dimensions' => $this->getImageDimensions($processedImage),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در آپلود فایل',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadVideo(Request $request)
    {
        $request->validate([
            'video' => 'required|mimes:mp4,mov,avi,wmv|max:51200', // 50MB max
            'type' => 'in:post,story',
        ]);

        try {
            $file = $request->file('video');
            $type = $request->input('type', 'post');

            // Generate unique filename
            $filename = $this->generateFilename($file->getClientOriginalExtension());
            $path = "media/{$type}s/videos/" . date('Y/m/d');
            $fullPath = "{$path}/{$filename}";

            // Store video
            Storage::disk('public')->putFileAs($path, $file, $filename);

            $url = Storage::disk('public')->url($fullPath);
            $size = $file->getSize();

            return response()->json([
                'message' => 'ویدیو با موفقیت آپلود شد',
                'data' => [
                    'url' => $url,
                    'path' => $fullPath,
                    'filename' => $filename,
                    'size' => $size,
                    'type' => $type,
                    'duration' => null, // Could be extracted using FFmpeg
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در آپلود ویدیو',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadDocument(Request $request)
    {
        $request->validate([
            'document' => 'required|mimes:pdf,doc,docx,txt|max:20480', // 20MB max
        ]);

        try {
            $file = $request->file('document');

            // Generate unique filename
            $filename = $this->generateFilename($file->getClientOriginalExtension());
            $path = "media/documents/" . date('Y/m/d');
            $fullPath = "{$path}/{$filename}";

            // Store document
            Storage::disk('public')->putFileAs($path, $file, $filename);

            $url = Storage::disk('public')->url($fullPath);
            $size = $file->getSize();

            return response()->json([
                'message' => 'سند با موفقیت آپلود شد',
                'data' => [
                    'url' => $url,
                    'path' => $fullPath,
                    'filename' => $filename,
                    'size' => $size,
                    'original_name' => $file->getClientOriginalName(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در آپلود سند',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteMedia(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        try {
            $path = $request->input('path');

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);

                // Also delete thumbnail if exists
                $thumbnailPath = str_replace('/media/', '/media/thumbnails/', $path);
                if (Storage::disk('public')->exists($thumbnailPath)) {
                    Storage::disk('public')->delete($thumbnailPath);
                }

                return response()->json(['message' => 'فایل با موفقیت حذف شد']);
            }

            return response()->json(['message' => 'فایل یافت نشد'], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در حذف فایل',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function generateFilename($extension)
    {
        return Str::uuid() . '.' . $extension;
    }

    private function processImage($file, $type, $quality)
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file);

        // Set dimensions based on type
        switch ($type) {
            case 'avatar':
                $image->cover(400, 400);

                break;
            case 'cover':
                $image->cover(1200, 400);

                break;
            case 'story':
                $image->cover(1080, 1920);

                break;
            case 'post':
            default:
                // Maintain aspect ratio, max width 1200px
                if ($image->width() > 1200) {
                    $image->scale(width: 1200);
                }

                break;
        }

        // Apply quality and return encoded image
        return $image->toJpeg($quality)->toString();
    }

    private function getImageDimensions($imageData)
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($imageData);

        return [
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }
}
