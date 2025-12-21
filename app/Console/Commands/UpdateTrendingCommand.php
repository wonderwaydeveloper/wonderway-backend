<?php

namespace App\Console\Commands;

use App\Services\TrendingService;
use Illuminate\Console\Command;

class UpdateTrendingCommand extends Command
{
    protected $signature = 'trending:update {--force : Force update even if recently updated}';
    protected $description = 'Update trending calculations for hashtags, posts, and users';

    private $trendingService;

    public function __construct(TrendingService $trendingService)
    {
        parent::__construct();
        $this->trendingService = $trendingService;
    }

    public function handle()
    {
        $this->info('Starting trending calculations update...');

        $startTime = microtime(true);

        try {
            $result = $this->trendingService->updateTrendingScores();

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            $this->info("Trending calculations updated successfully!");
            $this->table(
                ['Component', 'Status', 'Updated At'],
                [
                    ['Hashtags', $result['hashtags_updated'] ? '✓ Updated' : '✗ Failed', $result['timestamp']],
                    ['Posts', $result['posts_updated'] ? '✓ Updated' : '✗ Failed', $result['timestamp']],
                    ['Users', $result['users_updated'] ? '✓ Updated' : '✗ Failed', $result['timestamp']],
                ]
            );

            $this->info("Execution time: {$executionTime}ms");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to update trending calculations: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}