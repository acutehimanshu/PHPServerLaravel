<?php 
namespace App\Services;
use App\Models\NotificationLog;

class SmsService
{
    public static function send($user, $message)
    {
        if (!config('services.sms.enabled')) {
            NotificationLog::create([
                'user_id' => $user->id,
                'channel' => 'sms',
                'to' => $user->profile->phone ?? '',
                'message' => $message,
                'status' => 'failed',
                'provider_response' => 'SMS disabled in env'
            ]);
            return;
        }

        // Call SMS provider here

        NotificationLog::create([
            'user_id' => $user->id,
            'channel' => 'sms',
            'to' => $user->profile->phone,
            'message' => $message,
            'status' => 'sent'
        ]);
    }
}
