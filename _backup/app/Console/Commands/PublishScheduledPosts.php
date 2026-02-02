<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Post;
use App\Services\FacebookService;
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

    public function __construct(FacebookService $facebookService)
    {
        parent::__construct();
        $this->facebookService = $facebookService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for scheduled posts...');

        $posts = Post::where('is_posted', false)
            ->where('schedule_at', '<=', Carbon::now())
            ->get();

        if ($posts->isEmpty()) {
            $this->info('No posts ready based on schedule_at <= now.');
            return;
        }

        foreach ($posts as $post) {
            $this->processPost($post);
        }

        $this->info('Batch processing completed.');
    }

    protected function processPost(Post $post)
    {
        $this->info("Processing Post ID: {$post->id}");

        try {
            $fbPostId = $this->facebookService->publishPost($post->content);

            $post->update([
                'is_posted' => true,
                'fb_post_id' => $fbPostId,
            ]);

            $this->info("Success! Published as FB ID: {$fbPostId}");

        } catch (\Exception $e) {
            $this->error("Failed to publish Post ID {$post->id}: " . $e->getMessage());
            // Log is handled in Service, but we can add context here if needed
            // We continue loop so one failure doesn't stop others
        }
    }
}
