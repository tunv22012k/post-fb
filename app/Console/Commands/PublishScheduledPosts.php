<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Post;
use App\Services\FacebookService;
use App\Services\GeminiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PublishScheduledPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facebook:post';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish scheduled posts to Facebook Page';

    protected FacebookService $facebookService;
    protected GeminiService $geminiService;

    public function __construct(FacebookService $facebookService, GeminiService $geminiService)
    {
        parent::__construct();
        $this->facebookService = $facebookService;
        $this->geminiService = $geminiService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for scheduled jobs (PublicationJob)...');

        // Find jobs that are QUEUED and scheduled time has passed
        $jobs = \App\Models\PublicationJob::with(['channel', 'variant'])
            ->where('status', 'QUEUED')
            ->where('scheduled_at', '<=', Carbon::now())
            ->get();

        if ($jobs->isEmpty()) {
            $this->info('No queued jobs ready based on scheduled_at <= now.');
            return;
        }

        foreach ($jobs as $job) {
            Log::channel('facebook')->info("Starting to process Job ID: {$job->id}");
            $this->processJob($job);
        }

        $this->info('Batch processing completed.');
    }

    protected function processJob(\App\Models\PublicationJob $job)
    {
        $this->info("Processing Job ID: {$job->id} for Channel: {$job->channel->name}");

        try {
            $job->update(['status' => 'PUBLISHING']);

            $content = $job->variant->final_content;
            $accessToken = $job->channel->access_token;
            
            // Check for Media Asset for this specific channel
            $mediaAssets = json_decode($job->variant->media_assets ?? '[]', true);
            $imagePath = $mediaAssets[$job->channel_id] ?? null;

            if (!empty($imagePath) && file_exists($imagePath)) {
                $this->info("Publishing with Image: " . basename($imagePath));
                $fbPostId = $this->facebookService->publishPhotoToPage($accessToken, $content, $imagePath);
            } else {
                if (empty($content)) {
                    throw new \Exception("Content is empty.");
                }
                $fbPostId = $this->facebookService->publishPostToPage($accessToken, $content);
            }

            $job->update([
                'status' => 'PUBLISHED',
                'platform_response_id' => $fbPostId,
                'error_log' => null,
            ]);

            $this->info("Success! Published as FB ID: {$fbPostId}");
            Log::channel('facebook')->info("Successfully published Job ID: {$job->id}. FB Post ID: {$fbPostId}");

        } catch (\Exception $e) {
            $job->update([
                'status' => 'FAILED',
                'error_log' => $e->getMessage(),
            ]);
            $this->error("Failed to publish Job ID {$job->id}: " . $e->getMessage());
            Log::channel('facebook')->error("Failed to publish Job ID {$job->id}: " . $e->getMessage());
        }
    }
}
