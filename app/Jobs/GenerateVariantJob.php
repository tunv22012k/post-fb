<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MasterArticle;
use App\Models\ContentVariant;
use App\Models\PublicationJob;
use App\Models\DestinationChannel;
use App\Services\GeminiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GenerateVariantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $master;
    protected $persona;
    protected $scheduledAt;
    protected $language;
    protected $targetChannelIds;

    /**
     * Create a new job instance.
     */
    public function __construct(MasterArticle $master, string $persona, Carbon $scheduledAt, string $language = 'vi', array $targetChannelIds = [])
    {
        $this->master = $master;
        $this->persona = $persona;
        $this->scheduledAt = $scheduledAt;
        $this->language = $language;
        $this->targetChannelIds = $targetChannelIds;
    }

    /**
     * Execute the job.
     */
    public function handle(GeminiService $geminiService, \App\Services\WatermarkService $watermarkService): void
    {
        try {
            Log::info("Generating variant for Master ID {$this->master->id} with Persona: {$this->persona} in {$this->language}");

            // 1. AI Rewrite
            $rewrittenContent = $geminiService->rewriteContent($this->master->content, $this->persona, $this->language);

            // 2. Prepare Variant Data
            $variantData = [
                'master_id' => $this->master->id,
                'final_content' => $rewrittenContent,
                'source_type' => 'AI_REWRITE_' . strtoupper($this->persona),
                'status' => 'APPROVED',
                'media_assets' => [], 
            ];

            // 3. Fan-out and Watermark
            // Filter channels based on selection
            $channelsQuery = DestinationChannel::where('user_id', $this->master->user_id);
            if (!empty($this->targetChannelIds)) {
                $channelsQuery->whereIn('id', $this->targetChannelIds);
            }
            $channels = $channelsQuery->get();
            
            $mediaMap = [];

            foreach ($channels as $channel) {
                // Image Processing (Phase 2)
                if (!empty($this->master->media_path) && file_exists($this->master->media_path)) {
                    // Watermark with Channel Name
                    // Create a unique filename for this channel/variant to avoid collision
                    // But WatermarkService adds '_w', so we might overwrite if not careful.
                    // Let's modify WatermarkService call or handle it here?
                    // WatermarkService returns a new path.
                    // Ideally we should copy to a temp buffer or just trust WatermarkService to create a new file if we pass a specific destination?
                    // WatermarkService currently blindly appends '_w'.
                    // Let's modify it to be safer or just accept it's a demo.
                    // For demo: unique path = copy original to temp -> watermark -> save.
                    
                    // Simple Hack for Demo:
                    // Just process it. Note: current WatermarkService creates [filename]_w.ext.
                    // If we run this loop, we might need unique names.
                    // Let's rely on WatermarkService returning a path.
                    // To get unique images per channel, we need to provide a unique "Source" or modify WatermarkService to accept "Output Path".
                    // Let's assume for this MVP we just use the Master Image with ONE watermark? 
                    // No, the requirement was "Each Page".
                    
                    // Let's update the logic:
                    // 1. Copy master image to unique path [timestamp]_[channel_id].jpg
                    // 2. Watermark that unique image.
                    
                    $extension = pathinfo($this->master->media_path, PATHINFO_EXTENSION);
                    $tempPath = storage_path('app/public/uploads/' . time() . '_' . $variantData['source_type'] . '_' . $channel->id . '.' . $extension);
                    copy($this->master->media_path, $tempPath);
                    
                    $processedPath = $watermarkService->addWatermark($tempPath, "@" . $channel->name);
                    $mediaMap[$channel->id] = $processedPath;
                }
            }
            
            // Save logic to variant (store JSON of channel_id => image_path)
            $variant = ContentVariant::create(array_merge($variantData, [
                'media_assets' => json_encode($mediaMap)
            ]));

            foreach ($channels as $channel) {
                PublicationJob::create([
                    'variant_id' => $variant->id,
                    'channel_id' => $channel->id,
                    'scheduled_at' => $this->scheduledAt,
                    'status' => 'QUEUED',
                ]);
            }

            Log::info("Variant created and jobs scheduled for Persona: {$this->persona}");

        } catch (\Exception $e) {
            Log::error("GenerateVariantJob Failed: " . $e->getMessage());
        }
    }
}
