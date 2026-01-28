<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $fillable = [
        "id", "user_id", "first_name", "last_name", 
        "phone", "country_code", "dob", "gender", "avatar", 
        "address", "city", "state", "country", "zip", "timezone", 
        "language", "device_info", "metadata", "created_at", "updated_at"
    ];

    protected $casts = [
        'device_info' => 'array',
        'metadata' => 'array'
    ];
}
