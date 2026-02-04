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
    // Run the specific command directly in-process to avoid Windows/PHP process spawning issues
    \Illuminate\Support\Facades\Artisan::call('facebook:post');
    $output = \Illuminate\Support\Facades\Artisan::output();
    
    return response(
        '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="60"><title>Scheduler Running</title></head>' .
        '<body style="font-family: monospace; background: #000; color: #0f0; padding: 20px;">' .
        '<h1>Direct Command Execution Log (facebook:post)</h1>' .
        '<p>This page will auto-refresh every 60 seconds.</p>' .
        '<hr><pre>' . $output . '</pre>' .
        '<p>Last run: ' . now() . '</p></body></html>'
    , 200)->header('Content-Type', 'text/html');
});

Route::view('/privacy-policy', 'privacy-policy')->name('privacy.policy');
