<?php

namespace App\Services\Kiosk;

use Illuminate\Support\Facades\Log;

class KioskAuditLogger
{
    public function log(
        string $action,
        bool $success,
        ?string $identityNo = null,
        ?string $kioskId = null,
        ?string $message = null,
        array $context = [],
    ): void {
        Log::channel('kiosk_audit')->info($action, array_filter([
            'success' => $success,
            'kiosk_id' => $kioskId ?: config('kiosk.id'),
            'tc_hash' => $identityNo ? $this->hashTc($identityNo) : null,
            'message' => $message,
            'ip' => request()?->ip(),
            ...$context,
        ], static fn ($v) => $v !== null && $v !== ''));
    }

    public function hashTc(string $identityNo): string
    {
        return hash_hmac('sha256', preg_replace('/\D/', '', $identityNo) ?? '', (string) config('app.key'));
    }
}
