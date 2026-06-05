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
     * @param string $eventCode
     * @param string|null $recipientEmail
     * @param string|null $recipientWA
     * @param array<string, mixed> $data
     */
    public static function send(string $eventCode, ?string $recipientEmail, ?string $recipientWA, array $data = []): void
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
        $emailContent = self::parseTemplate(strval($template->konten_email), $data);
        $waContent    = self::parseTemplate(strval($template->konten_wa), $data);
        $subject      = self::parseTemplate(strval($template->subjek_email), $data);

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

    /**
     * @param string $content
     * @param array<string, mixed> $data
     * @return string
     */
    private static function parseTemplate(string $content, array $data): string
    {
        if (empty($content)) return "";
        
        foreach ($data as $key => $value) {
            $valStr = is_scalar($value) ? (string) $value : '';
            $content = str_replace("{" . strtoupper($key) . "}", $valStr, $content);
        }
        
        return $content;
    }

    private static function sendWA(string $phone, string $message): void
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
