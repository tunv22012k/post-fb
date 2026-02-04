<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterArticle extends Model
{
    protected $guarded = [];

    public function variants()
    {
        return $this->hasMany(ContentVariant::class, 'master_id');
    }
}
