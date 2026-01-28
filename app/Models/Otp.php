<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $fillable = [
        'user_id','type','otp','expires_at','sent_via'
    ];

    protected $dates = ['expires_at','verified_at'];
}
