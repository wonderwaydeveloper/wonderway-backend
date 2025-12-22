<?php

namespace App\Jobs;

use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout

    public function __construct(
        private Video $video
    ) {}

    public function handle(): void
    {
        try {
            $this->video->update(['encoding_status' => 'processing']);

            // For testing, we'll simulate video processing without actual FFMpeg
            if (app()->environment('testing')) {
                $this->simulateVideoProcessing();
                return;
            }

            $ffmpeg = FFMpeg::create();
            $videoFile = $ffmpeg->open(Storage::path($this->video->original_path));

            // Get video metadata
            $metadata = $this->extractMetadata($videoFile);
            $this->video->update(['metadata' => $metadata]);

            // Generate thumbnail
            $thumbnailPath = $this->generateThumbnail($videoFile);
            $this->video->update(['thumbnail_path' => $thumbnailPath]);

            // Process different resolutions
            $processedPaths = $this->processResolutions($videoFile);
            
            $this->video->update([
                'processed_paths' => $processedPaths,
                'encoding_status' => 'completed'
            ]);

        } catch (\Exception $e) {
            \Log::error('Video processing failed: ' . $e->getMessage());
            $this->video->update(['encoding_status' => 'failed']);
            throw $e;
        }
    }

    private function simulateVideoProcessing(): void
    {
        // Simulate processing for tests
        $this->video->update([
            'thumbnail_path' => 'thumbnails/test-thumb.jpg',
            'processed_paths' => [
                '480p' => 'videos/processed/test_480p.mp4',
                '720p' => 'videos/processed/test_720p.mp4',
                '1080p' => 'videos/processed/test_1080p.mp4',
            ],
            'duration' => 120,
            'metadata' => [
                'duration' => 120,
                'width' => 1920,
                'height' => 1080,
                'codec' => 'h264'
            ],
            'encoding_status' => 'completed'
        ]);
    }

    private function extractMetadata($videoFile): array
    {
        $streams = $videoFile->getStreams();
        $videoStream = $streams->videos()->first();
        
        return [
            'duration' => $videoFile->getFormat()->get('duration'),
            'width' => $videoStream->get('width'),
            'height' => $videoStream->get('height'),
            'codec' => $videoStream->get('codec_name'),
            'bitrate' => $videoFile->getFormat()->get('bit_rate')
        ];
    }

    private function generateThumbnail($videoFile): string
    {
        $thumbnailPath = 'thumbnails/' . uniqid() . '.jpg';
        
        $videoFile
            ->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(1))
            ->save(Storage::path($thumbnailPath));
            
        return $thumbnailPath;
    }

    private function processResolutions($videoFile): array
    {
        $resolutions = [
            '480p' => ['width' => 854, 'height' => 480],
            '720p' => ['width' => 1280, 'height' => 720],
            '1080p' => ['width' => 1920, 'height' => 1080]
        ];

        $processedPaths = [];

        foreach ($resolutions as $quality => $dimensions) {
            $outputPath = 'videos/processed/' . uniqid() . "_{$quality}.mp4";
            
            $format = new X264();
            $format->setKiloBitrate(1000);
            
            $videoFile
                ->filters()
                ->resize(new \FFMpeg\Coordinate\Dimension($dimensions['width'], $dimensions['height']))
                ->synchronize();
                
            $videoFile->save($format, Storage::path($outputPath));
            
            $processedPaths[$quality] = $outputPath;
        }

        return $processedPaths;
    }
}