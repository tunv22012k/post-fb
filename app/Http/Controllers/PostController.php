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

        $channelCount = DestinationChannel::where('user_id', Auth::id())->count();
        if ($channelCount == 0) {
            return redirect()->route('dashboard')->with('error', 'Please connect Facebook pages first!');
        }

        return view('posts.create', ['count' => $channelCount]);
    }

    /**
     * Store a newly created post in storage (Fan-out Flow).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string|min:10',
            'scheduled_at' => 'required|date|after:now',
        ]);

        DB::beginTransaction();
        try {
            // 1. Create Master Article
            $master = MasterArticle::create([
                'user_id' => Auth::id(),
                'content' => $validated['content'],
                'status' => 'PENDING',
                'source_url' => $request->input('source_url'),
            ]);

            // 2. Create Content Variant (Simplest flow: 1 variant = original)
            $variant = ContentVariant::create([
                'master_id' => $master->id,
                'final_content' => $validated['content'], // In real app, AI modifies this
                'source_type' => 'ORIGINAL',
                'status' => 'APPROVED', // Skip review for demo
            ]);

            // 3. Fan-out to all Channels (Multiplexing)
            $channels = DestinationChannel::where('user_id', Auth::id())->get();
            $jobsCount = 0;

            foreach ($channels as $channel) {
                PublicationJob::create([
                    'variant_id' => $variant->id,
                    'channel_id' => $channel->id,
                    'scheduled_at' => Carbon::parse($validated['scheduled_at']),
                    'status' => 'QUEUED',
                ]);
                $jobsCount++;
            }

            DB::commit();

            return redirect()->route('dashboard')
                ->with('success', "Success! System created 1 Master Article, 1 Variant, and fan-out to {$jobsCount} scheduled jobs.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
