<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentVariant extends Model
{
    protected $guarded = [];

    public function jobs()
    {
        return $this->hasMany(PublicationJob::class, 'variant_id');
    }
}
