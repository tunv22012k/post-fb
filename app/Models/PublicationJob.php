<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicationJob extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function channel()
    {
        return $this->belongsTo(DestinationChannel::class, 'channel_id');
    }
    
    public function variant()
    {
        return $this->belongsTo(ContentVariant::class, 'variant_id');
    }
}
