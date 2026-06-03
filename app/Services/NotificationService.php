<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\DynamicNotificationMail;

class NotificationService
{
    /**
     * Kirim notifikasi berdasarkan event
     */
    public static function send($eventCode, $recipientEmail, $recipientWA, $data = [])
    {
        $template = DB::table('notifikasi_templates')
            ->where('kode_event', $eventCode)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            Log::warning("Notification template not found or inactive for event: {$eventCode}");
            return;
        }

        // Replace Placeholders
        $emailContent = self::parseTemplate($template->konten_email, $data);
        $waContent    = self::parseTemplate($template->konten_wa, $data);
        $subject      = self::parseTemplate($template->subjek_email, $data);

        // Send Email
        if ($recipientEmail && !empty($emailContent)) {
            try {
                Mail::to($recipientEmail)->send(new DynamicNotificationMail($subject, $emailContent));
            } catch (\Exception $e) {
                Log::error("Failed to send email to {$recipientEmail}: " . $e->getMessage());
            }
        }

        // Send WhatsApp (Simulation / API Call)
        if ($recipientWA && !empty($waContent)) {
            self::sendWA($recipientWA, $waContent);
        }
    }

    private static function parseTemplate($content, $data)
    {
        if (empty($content)) return "";
        
        foreach ($data as $key => $value) {
            $content = str_replace("{" . strtoupper($key) . "}", $value, $content);
        }
        
        return $content;
    }

    private static function sendWA($phone, $message)
    {
        // Simulasi pengiriman via API Gateway (misal: Fonnte/Wootils)
        Log::info("WA SENT TO {$phone}: {$message}");
        
        // Contoh API Call:
        /*
        Http::withHeaders(['Authorization' => 'token-anda'])
            ->post('https://api.wa-gateway.com/send', [
                'target' => $phone,
                'message' => $message
            ]);
        */
    }
}
