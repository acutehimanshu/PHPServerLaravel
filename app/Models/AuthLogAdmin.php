<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthLogAdmin extends Model
{
    protected $fillable = [
        'admin_id','email','action','status',
        'ip_address','user_agent','device_info','location','message'
    ];

    protected $casts = [
        'device_info' => 'array',
        'location' => 'array'
    ];
}
