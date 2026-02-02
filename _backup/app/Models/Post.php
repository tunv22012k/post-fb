<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory;

    protected $fillable = [
        'content',
        'schedule_at',
        'is_posted',
        'fb_post_id',
    ];

    protected $casts = [
        'schedule_at' => 'datetime',
        'is_posted' => 'boolean',
    ];
}
