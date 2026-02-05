<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\MasterArticle;
use App\Models\ContentVariant;
use App\Models\PublicationJob;
use App\Models\DestinationChannel;
use App\Models\User;

class PostController extends Controller
{
    /**
     * Show the form for creating a new post.
     */
    public function create()
    {
        // DEMO ONLY: Auto-login
        if (!Auth::check()) {
             $user = User::first();
             if ($user) Auth::login($user);
        }

        $channels = DestinationChannel::where('user_id', Auth::id())->get();
        if ($channels->count() == 0) {
            return redirect()->route('dashboard')->with('error', 'Please connect Facebook pages first!');
        }

        return view('posts.create', ['channels' => $channels, 'count' => $channels->count()]);
    }

    /**
     * Store a newly created post in storage (Fan-out Flow).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string|min:10',
            'scheduled_at' => 'required|date|after:now',
            'image' => 'nullable|image|max:5120', // Max 5MB
            'distribution_config' => 'required|json', // New Payload
        ]);

        DB::beginTransaction();
        try {
            $mediaPath = null;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('uploads', $filename, 'public'); 
                $mediaPath = storage_path('app/public/' . $path);
            }

            // 1. Create Master Article
            $master = MasterArticle::create([
                'user_id' => Auth::id(),
                'content' => $validated['content'],
                'status' => 'PROCESSING',
                'source_url' => $request->input('source_url'),
                'media_path' => $mediaPath,
            ]);

            $configs = json_decode($request->input('distribution_config'), true);
            $totalJobs = 0;
            $totalVariants = 0;

            foreach ($configs as $group) {
                $channelIds = $group['channels'] ?? [];
                if (empty($channelIds)) continue;

                $useAi = $group['use_ai'] ?? false;
                $baseSchedule = Carbon::parse($validated['scheduled_at']);

                if (!$useAi) {
                    // BRANCH A: Direct Mode (Staging)
                    // Create ONE variant for this group (Original content)
                    $variant = ContentVariant::create([
                        'master_id' => $master->id,
                        'final_content' => $validated['content'],
                        'source_type' => 'ORIGINAL',
                        'status' => 'WAITING_REVIEW', // Pending Approval
                        'media_assets' => null, // No watermark yet
                    ]);
                    $totalVariants++;

                    foreach ($channelIds as $channelId) {
                         PublicationJob::create([
                            'variant_id' => $variant->id,
                            'channel_id' => $channelId,
                            'scheduled_at' => $baseSchedule,
                            'status' => 'HOLD', // Staging
                        ]);
                        $totalJobs++;
                    }
                } else {
                    // BRANCH B: AI Factory Mode (Unique Variants per Channel)
                    /*
                     * Requirement: 
                     * "Nếu chọn 1 thể loại thì gen 1 thể loại đó ra nhiều bản AI khác nhau và không được trùng câu từ"
                     * -> We generate a UNIQUE variant for EACH channel.
                     * "Nếu chọn 2 thể loại thì tự random"
                     * -> We distribute styles to channels.
                     */
                    
                    $styles = $group['styles'] ?? ['KOL']; // Default
                    if (empty($styles)) $styles = ['KOL'];
                    
                    // Shuffle styles for random distribution if styles > 1
                    // But maybe we want consistent rotation? Let's shuffle once.
                    shuffle($styles);
                    
                    $language = $group['language'] ?? 'vi';

                    foreach ($channelIds as $index => $channelId) {
                        // Assign Style (Round Robin)
                        $style = $styles[$index % count($styles)];
                        
                        // Dispatch Unique Job
                        // By passing a SINGLE channel ID to the job, we ensure this job generates a variant 
                        // specifically for this channel.
                        // Wait, GenerateVariantJob dispatch signature currently accepts array.
                        // I will pass [channelId].
                        
                        \App\Jobs\GenerateVariantJob::dispatch(
                             $master, 
                             $style, 
                             $baseSchedule,
                             $language,
                             [$channelId] // Target only this channel
                        );
                        $totalJobs++; // Approximate, actual job creation is async
                        $totalVariants++;
                    }
                }
            }

            DB::commit();

            return redirect()->route('dashboard')
                ->with('success', "Success! Configured {$totalJobs} jobs across " . count($configs) . " groups. AI Agents are generating unique variants.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
