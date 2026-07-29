<?php

namespace App\Console\Commands;

use App\Services\Kiosk\KioskHeartbeatService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class KioskHeartbeatCheck extends Command
{
    protected $signature = 'kiosk:heartbeat-check';
    protected $description = 'Kiosk cihazlarının heartbeat durumunu kontrol eder, çevrimdışıysa Telegram bildirimi gönderir';

    private const ALERT_SENT_PREFIX = 'kiosk_hb_alert:';
    private const THRESHOLD_MINUTES = 3;

    public function handle(KioskHeartbeatService $heartbeat): int
    {
        $kioskIds = config('kiosk.monitored_kiosks', ['kiosk-1']);

        foreach ($kioskIds as $kioskId) {
            $isOffline = $heartbeat->isOffline($kioskId, self::THRESHOLD_MINUTES);
            $alertKey = self::ALERT_SENT_PREFIX.$kioskId;

            if ($isOffline && ! Cache::has($alertKey)) {
                $lastSeen = $heartbeat->lastSeen($kioskId);
                $lastSeenText = $lastSeen ? \Carbon\Carbon::parse($lastSeen)->format('d.m.Y H:i:s') : 'hiç bağlanmadı';

                $msg = "🔴 <b>Kiosk Çevrimdışı!</b>\n\n"
                    ."Cihaz: <b>{$kioskId}</b>\n"
                    ."Son görülme: {$lastSeenText}\n"
                    ."Cihaz {self::THRESHOLD_MINUTES} dakikadır yanıt vermiyor.\n"
                    ."Cihaz kapanmış veya ağ bağlantısı kopmuş olabilir.";

                KioskHeartbeatService::sendTelegram($msg);
                Cache::put($alertKey, true, now()->addMinutes(30));

                $this->warn("{$kioskId} çevrimdışı — Telegram bildirimi gönderildi.");
            } elseif (! $isOffline && Cache::has($alertKey)) {
                Cache::forget($alertKey);

                $msg = "✅ <b>Kiosk Tekrar Çevrimiçi</b>\n\n"
                    ."Cihaz: <b>{$kioskId}</b>\n"
                    ."Bağlantı yeniden kuruldu.";

                KioskHeartbeatService::sendTelegram($msg);

                $this->info("{$kioskId} tekrar çevrimiçi — bildirim gönderildi.");
            } else {
                $this->info("{$kioskId}: ".($isOffline ? 'çevrimdışı (bildirim zaten gönderildi)' : 'çevrimiçi'));
            }
        }

        return self::SUCCESS;
    }
}
