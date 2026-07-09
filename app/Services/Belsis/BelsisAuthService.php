<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use Illuminate\Support\Facades\Cache;

class BelsisAuthService
{
    private const CACHE_KEY = 'belsis.session';

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

    public function forgetSession(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function openSession(): array
    {
        $username = config('belsis.username');
        $password = config('belsis.password');

        if (empty($username) || $password === null || $password === '') {
            throw new BelsisException(
                'Belsis kullanıcı adı veya şifre yapılandırılmamış. .env dosyasında BELSIS_USERNAME ve BELSIS_PASSWORD ayarlayın.',
            );
        }

        $result = $this->client->callTahsilat('login', [
            'kullaniciAdi' => $username,
            'sifre'        => $password,
        ], wrapper: 'girdi');

        $oturumKimligi = $result['oturumKimligi'] ?? null;

        if (empty($oturumKimligi)) {
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
            'ipAdresi'      => config('belsis.ip_address'),
            'oturumKimligi' => $session['oturumKimligi'],
        ];
    }
}
