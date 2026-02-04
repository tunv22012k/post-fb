<?php

use App\Http\Controllers\PostController;
use App\Http\Controllers\FacebookController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Facebook Auth Routes
Route::get('/auth/facebook', [FacebookController::class, 'redirectToFacebook'])->name('facebook.login');
Route::get('/auth/facebook/callback', [FacebookController::class, 'handleFacebookCallback']);

// Dashboard
Route::get('/dashboard', function () {
    // DEMO ONLY: Auto-login first user if not logged in
    if (!Auth::check()) {
        $user = User::first();
        if ($user) Auth::login($user);
    }
    
    $channels = DB::table('destination_channels')
        ->where('user_id', Auth::id() ?? 1)
        ->get();
        
    return view('dashboard', ['channels' => $channels]);
})->name('dashboard');

// Scheduling Flow
Route::get('/posts/create', [PostController::class, 'create'])->name('posts.create');
Route::post('/posts', [PostController::class, 'store'])->name('posts.store');

// Test Routes
Route::get('/run-schedule', function () {
    \Illuminate\Support\Facades\Artisan::call('schedule:run');
    return response(\Illuminate\Support\Facades\Artisan::output(), 200)->header('Content-Type', 'text/plain');
});
