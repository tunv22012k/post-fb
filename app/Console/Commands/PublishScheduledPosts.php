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
        // Log::channel('facebook')->info('Scheduler started check.'); 
        $this->info('Checking for scheduled posts...');

        $posts = Post::where('is_posted', false)
            ->where('schedule_at', '<=', Carbon::now())
            ->get();

        if ($posts->isEmpty()) {
            $this->info('No posts ready based on schedule_at <= now.');
            return;
        }

        foreach ($posts as $post) {
            Log::channel('facebook')->info("Starting to process Post ID: {$post->id}");
            $this->processPost($post);
        }

        $this->info('Batch processing completed.');
    }

    protected function processPost(Post $post)
    {
        $this->info("Processing Post ID: {$post->id}");

        try {
            // Check if content is empty and needs generation from prompt
            if (empty($post->content) && !empty($post->prompt)) {
                $this->info("Generating content from prompt...");
                Log::channel('facebook')->info("Generating content for Post ID: {$post->id} using Gemini.");
                
                $generatedContent = $this->geminiService->generateContent($post->prompt);
                
                Log::channel('facebook')->info("Gemini Generated Content for Post ID {$post->id}: \n" . $generatedContent);

                $post->update(['content' => $generatedContent]);
                $this->info("Content generated successfully.");
            }

            // Ensure content exists before publishing
            if (empty($post->content)) {
                throw new \Exception("Post content is empty and no prompt available.");
            }

            $fbPostId = $this->facebookService->publishPost($post->content);

            $post->update([
                'is_posted' => true,
                'fb_post_id' => $fbPostId,
            ]);

            $this->info("Success! Published as FB ID: {$fbPostId}");
            Log::channel('facebook')->info("Successfully published Post ID: {$post->id}. FB Post ID: {$fbPostId}");

        } catch (\Exception $e) {
            $this->error("Failed to publish Post ID {$post->id}: " . $e->getMessage());
            Log::channel('facebook')->error("Failed to publish Post ID {$post->id}: " . $e->getMessage());
        }
    }
}
