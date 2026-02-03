<?php

use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('posts.create');
});

Route::get('/posts/create', [PostController::class, 'create'])->name('posts.create');
Route::post('/posts', [PostController::class, 'store'])->name('posts.store');

Route::get('/run-schedule', function () {
    \Illuminate\Support\Facades\Artisan::call('schedule:run');
    $output = \Illuminate\Support\Facades\Artisan::output();
    return response($output, 200)->header('Content-Type', 'text/plain');
});
