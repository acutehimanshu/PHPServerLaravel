<?php 
namespace App\Services;
use Illuminate\Support\Facades\Mail;
use App\Models\NotificationLog;

class EmailService
{
    public static function send($user, $subject, $message)
    {
        try {
            Mail::raw($message, fn($mail) =>
                $mail->to($user->email)->subject($subject)
            );

            NotificationLog::create([
                'user_id' => $user->id,
                'channel' => 'email',
                'to' => $user->email,
                'subject' => $subject,
                'message' => $message,
                'status' => 'sent'
            ]);
        } catch (\Exception $e) {
            NotificationLog::create([
                'user_id' => $user->id,
                'channel' => 'email',
                'to' => $user->email,
                'message' => $message,
                'status' => 'failed',
                'provider_response' => $e->getMessage()
            ]);
        }
    }
}
