<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLogAdmin extends Model
{
    protected $fillable = [
        'admin_id','channel','to','subject','message','status','provider_response'
    ];
}
