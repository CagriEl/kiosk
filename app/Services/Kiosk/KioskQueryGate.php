<?php

namespace App\Services\Kiosk;

use App\Exceptions\BelsisException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class KioskQueryGate
{
    public function __construct(
        private readonly KioskAuditLogger $audit,
    ) {}

    public function kioskId(?string $headerId = null): string
    {
        $headerId = is_string($headerId) ? trim($headerId) : '';
        if ($headerId !== '' && preg_match('/^[a-zA-Z0-9._-]{1,64}$/', $headerId)) {
            return $headerId;
        }

        return (string) config('kiosk.id', 'kiosk-1');
    }

    public function assertNotRateLimited(string $kioskId, string $identityNo): void
    {
        $lockKey = $this->lockKey($kioskId, $identityNo);
        if (! Cache::has($lockKey)) {
            return;
        }

        $retryAfter = max(1, (int) Cache::get($lockKey.':until', time()) - time());
        throw new BelsisException(
            'Çok fazla başarısız deneme yapıldı. Lütfen '.$this->formatMinutes($retryAfter)
            .' sonra tekrar deneyiniz veya '.$this->supportPhone().' nolu hattı arayınız.',
        );
    }

    public function recordFailure(string $kioskId, string $identityNo, string $reason): void
    {
        $max = max(1, (int) config('kiosk.rate_limit.max_attempts', 5));
        $decay = max(60, (int) config('kiosk.rate_limit.decay_seconds', 600));
        $attemptKey = $this->attemptKey($kioskId, $identityNo);

        $attempts = (int) Cache::get($attemptKey, 0) + 1;
        Cache::put($attemptKey, $attempts, $decay);

        $this->audit->log('citizen_query', false, $identityNo, $kioskId, $reason, [
            'attempts' => $attempts,
            'max_attempts' => $max,
        ]);

        if ($attempts >= $max) {
            $until = time() + $decay;
            Cache::put($this->lockKey($kioskId, $identityNo), true, $decay);
            Cache::put($this->lockKey($kioskId, $identityNo).':until', $until, $decay);
            Cache::forget($attemptKey);
        }
    }

    public function recordSuccess(string $kioskId, string $identityNo): string
    {
        Cache::forget($this->attemptKey($kioskId, $identityNo));
        Cache::forget($this->lockKey($kioskId, $identityNo));
        Cache::forget($this->lockKey($kioskId, $identityNo).':until');

        $token = Str::random(48);
        $ttl = max(60, (int) config('kiosk.query_token_ttl', 900));
        Cache::put($this->tokenKey($token), [
            'tc' => preg_replace('/\D/', '', $identityNo),
            'kiosk_id' => $kioskId,
            'created_at' => time(),
        ], $ttl);

        $this->audit->log('citizen_query', true, $identityNo, $kioskId, 'Doğrulama başarılı');

        return $token;
    }

    public function assertQueryToken(string $token, string $identityNo, string $kioskId): void
    {
        $token = trim($token);
        if ($token === '') {
            throw new BelsisException('Oturum doğrulaması gerekli. Lütfen T.C. ve doğum tarihinizi tekrar giriniz.');
        }

        $payload = Cache::get($this->tokenKey($token));
        if (! is_array($payload)) {
            throw new BelsisException('Doğrulama süresi doldu. Lütfen T.C. ve doğum tarihinizi tekrar giriniz.');
        }

        $tc = preg_replace('/\D/', '', $identityNo) ?? '';
        if (($payload['tc'] ?? '') !== $tc) {
            $this->audit->log('debts_query', false, $identityNo, $kioskId, 'Token TC uyuşmazlığı');
            throw new BelsisException('Doğrulama geçersiz. Lütfen T.C. ve doğum tarihinizi tekrar giriniz.');
        }

        $this->audit->log('debts_query', true, $identityNo, $kioskId, 'Borç sorgusu yetkili');
    }

    public function forgetToken(?string $token): void
    {
        if (is_string($token) && $token !== '') {
            Cache::forget($this->tokenKey($token));
        }
    }

    private function attemptKey(string $kioskId, string $identityNo): string
    {
        return 'kiosk:rl:attempts:'.$kioskId.':'.$this->audit->hashTc($identityNo);
    }

    private function lockKey(string $kioskId, string $identityNo): string
    {
        return 'kiosk:rl:lock:'.$kioskId.':'.$this->audit->hashTc($identityNo);
    }

    private function tokenKey(string $token): string
    {
        return 'kiosk:query_token:'.$token;
    }

    private function supportPhone(): string
    {
        return (string) config('kiosk.support_phone', '444 01 39');
    }

    private function formatMinutes(int $seconds): string
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return $minutes.' dakika';
    }
}
