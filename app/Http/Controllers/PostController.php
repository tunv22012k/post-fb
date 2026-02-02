<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PostController extends Controller
{
    /**
     * Show the form for creating a new post.
     */
    public function create()
    {
        return view('posts.create');
    }

    /**
     * Store a newly created post in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'content' => 'nullable|string|max:2000',
            'prompt' => 'nullable|string|max:1000',
            'schedule_at' => 'required|date|after:now',
        ]);

        if (empty($validated['content']) && empty($validated['prompt'])) {
             return back()->withErrors(['content' => 'Please provide either Content or an AI Prompt.']);
        }

        Post::create([
            'content' => $validated['content'],
            'prompt' => $validated['prompt'] ?? null,
            'schedule_at' => Carbon::parse($validated['schedule_at']),
            'is_posted' => false,
        ]);

        return redirect()->route('posts.create')->with('success', 'Post scheduled successfully!');
    }
}
