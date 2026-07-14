<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BelsisAuthService
{
    private const CACHE_KEY = 'belsis.session';

    private const CACHE_KEY_TAHAKKUK = 'belsis.session.tahakkuk';

    private const CACHE_KEY_UNREACHABLE = 'belsis.session.unreachable';

    public function __construct(
        private readonly BelsisSoapClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getSession(): array
    {
        $this->assertHostReachable();

        try {
            return Cache::remember(self::CACHE_KEY, config('belsis.session_cache_ttl'), function () {
                return $this->openSession();
            });
        } catch (BelsisException $e) {
            $this->rememberUnreachableIfNeeded($e);
            throw $e;
        }
    }

    /**
     * tahakkukWebServis kendi bağımsız login/oturum mekanizmasına sahiptir —
     * tahsilatWebServis'ten alınan oturumKimligi burada geçerli değildir.
     *
     * @return array<string, mixed>
     */
    public function getTahakkukSession(): array
    {
        $this->assertHostReachable();

        try {
            return Cache::remember(self::CACHE_KEY_TAHAKKUK, config('belsis.session_cache_ttl'), function () {
                return $this->openTahakkukSession();
            });
        } catch (BelsisException $e) {
            $this->rememberUnreachableIfNeeded($e);
            throw $e;
        }
    }

    public function forgetSession(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY_TAHAKKUK);
        Cache::forget(self::CACHE_KEY_UNREACHABLE);
    }

    private function assertHostReachable(): void
    {
        $message = Cache::get(self::CACHE_KEY_UNREACHABLE);
        if (is_string($message) && $message !== '') {
            throw new BelsisException($message);
        }
    }

    private function rememberUnreachableIfNeeded(BelsisException $e): void
    {
        $message = $e->getMessage();

        if (
            str_contains($message, 'DNS:')
            || str_contains($message, 'ulaşılamadı')
            || str_contains($message, 'bağlanılamadı')
            || str_contains($message, 'baglanilamadi')
            || str_contains($message, 'zaman aşımı')
        ) {
            Cache::put(self::CACHE_KEY_UNREACHABLE, $message, 60);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function openSession(): array
    {
        return $this->login($this->client->callTahsilat(...));
    }

    /**
     * @return array<string, mixed>
     */
    public function openTahakkukSession(): array
    {
        return $this->login($this->client->callTahakkuk(...));
    }

    /**
     * @param  callable(string, array<string, mixed>, string): array<string, mixed>  $caller
     * @return array<string, mixed>
     */
    private function login(callable $caller): array
    {
        $username = config('belsis.username');
        $password = config('belsis.password');

        if (empty($username) || $password === null || $password === '') {
            throw new BelsisException(
                'Belsis kullanıcı adı veya şifre yapılandırılmamış. .env dosyasında BELSIS_USERNAME ve BELSIS_PASSWORD ayarlayın.',
            );
        }

        $result = $caller('login', [
            'kullaniciAdi' => $username,
            'sifre'        => $password,
        ], 'girdi');

        $oturumKimligi = $result['oturumKimligi'] ?? null;

        if (empty($oturumKimligi)) {
            Log::warning('Belsis login oturum kimliği boş döndü', ['result' => $result]);
            throw new BelsisException('Belsis oturum kimliği alınamadı.');
        }

        return [
            'oturumKimligi' => (string) $oturumKimligi,
            'guvenlikKodu'  => (string) ($result['guvenlikKodu'] ?? ''),
            'seriNo'        => (string) ($result['seriNo'] ?? ''),
            'kulNo'         => (string) ($result['kulNo'] ?? '0'),
            'makbuzNo'      => (string) ($result['makbuzNo'] ?? '0'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function baseParams(): array
    {
        $session = $this->getSession();

        return [
            'guvenlikKodu'  => $session['guvenlikKodu'],
            'ipAdresi'      => BelsisIpResolver::resolve(),
            'oturumKimligi' => $session['oturumKimligi'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function baseParamsTahakkuk(): array
    {
        $session = $this->getTahakkukSession();

        return [
            'guvenlikKodu'  => $session['guvenlikKodu'],
            'ipAdresi'      => BelsisIpResolver::resolve(),
            'oturumKimligi' => $session['oturumKimligi'],
        ];
    }
}
