<?php

namespace App\Services\Kiosk;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KioskHeartbeatService
{
    private const CACHE_PREFIX = 'kiosk_heartbeat:';

    /**
     * Cihazdan gelen heartbeat'i kaydet.
     */
    public function beat(string $kioskId): void
    {
        Cache::put(self::CACHE_PREFIX.$kioskId, now()->toIso8601String(), now()->addMinutes(10));
    }

    /**
     * Son heartbeat zamanını döndürür, yoksa null.
     */
    public function lastSeen(string $kioskId): ?string
    {
        return Cache::get(self::CACHE_PREFIX.$kioskId);
    }

    /**
     * Cihaz belirtilen süre içinde heartbeat göndermediyse true döner.
     */
    public function isOffline(string $kioskId, int $thresholdMinutes = 3): bool
    {
        $lastSeen = $this->lastSeen($kioskId);

        if ($lastSeen === null) {
            return true;
        }

        return now()->diffInMinutes(\Carbon\Carbon::parse($lastSeen)) >= $thresholdMinutes;
    }

    /**
     * Telegram'a bildirim gönder.
     */
    public static function sendTelegram(string $message): bool
    {
        $token = config('kiosk.telegram_bot_token', '');
        $chatId = config('kiosk.telegram_chat_id', '');

        if ($token === '' || $chatId === '') {
            Log::warning('Kiosk Telegram ayarları yapılmamış.');
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Kiosk Telegram bildirimi gönderilemedi: '.$e->getMessage());
            return false;
        }
    }
}
