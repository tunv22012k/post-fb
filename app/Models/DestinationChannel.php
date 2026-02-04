<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DestinationChannel extends Model
{
    protected $guarded = [];

    // Assuming we might encrypt token in future, but for now simple
    protected $hidden = ['access_token']; 
}
