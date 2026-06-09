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
     * @return array{oturumKimligi: string, guvenlikKodu: string}
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
     * @return array{oturumKimligi: string, guvenlikKodu: string}
     */
    public function openSession(): array
    {
        $username = config('belsis.username');
        $password = config('belsis.password');

        if (empty($username) || $password === null || $password === '') {
            throw new BelsisException(
                'Belsis kullanıcı adı veya şifre yapılandırılmamış. .env dosyasında BELSIS_USERNAME ve BELSIS_PASSWORD ayarlayın, ardından: php artisan config:clear',
            );
        }

        $result = $this->tryOturumAc($username, $password);

        $oturumKimligi = $result['oturumKimligi'] ?? $result['OturumKimligi'] ?? null;
        $guvenlikKodu  = $result['guvenlikKodu'] ?? $result['GuvenlikKodu'] ?? '';

        if (empty($oturumKimligi)) {
            throw new BelsisException('Belsis oturum kimliği alınamadı.');
        }

        return [
            'oturumKimligi' => (string) $oturumKimligi,
            'guvenlikKodu'  => (string) $guvenlikKodu,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tryOturumAc(string $username, string $password): array
    {
        $attempts = [
            ['kullaniciAdi' => $username, 'sifre' => $password],
            ['kullaniciAdi' => $username, 'kullaniciSifresi' => $password],
            ['kullaniciAdi' => $username, 'parola' => $password],
        ];

        $last = null;
        foreach ($attempts as $params) {
            try {
                return $this->client->callTahakkuk('oturumAc', array_merge($params, [
                    'ipAdresi' => config('belsis.ip_address'),
                ]), wrapGirdiParametre: false);
            } catch (BelsisException $e) {
                $last = $e;
            }
        }

        throw $last ?? new BelsisException('Belsis oturum açılamadı.');
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
