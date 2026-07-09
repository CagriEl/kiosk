<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BelsisAuthService
{
    private const CACHE_KEY = 'belsis.session';

    private const CACHE_KEY_TAHAKKUK = 'belsis.session.tahakkuk';

    public function __construct(
        private readonly BelsisSoapClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getSession(): array
    {
        return Cache::remember(self::CACHE_KEY, config('belsis.session_cache_ttl'), function () {
            return $this->openSession();
        });
    }

    /**
     * tahakkukWebServis kendi bağımsız login/oturum mekanizmasına sahiptir —
     * tahsilatWebServis'ten alınan oturumKimligi burada geçerli değildir.
     *
     * @return array<string, mixed>
     */
    public function getTahakkukSession(): array
    {
        return Cache::remember(self::CACHE_KEY_TAHAKKUK, config('belsis.session_cache_ttl'), function () {
            return $this->openTahakkukSession();
        });
    }

    public function forgetSession(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY_TAHAKKUK);
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
