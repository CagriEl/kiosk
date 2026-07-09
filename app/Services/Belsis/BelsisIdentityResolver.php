<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use App\Services\Belsis\Concerns\NormalizesBelsisLists;

class BelsisIdentityResolver
{
    use NormalizesBelsisLists;

    public function __construct(
        private readonly BelsisSoapClient $client,
        private readonly BelsisAuthService $auth,
    ) {}

    public function resolveGensicilNo(string $identityNo): string
    {
        $identityNo = trim($identityNo);

        if (! ctype_digit($identityNo)) {
            throw new BelsisException('Geçersiz kimlik numarası. Sadece rakam giriniz.');
        }

        if (strlen($identityNo) === 11) {
            return $this->findGensicilByTc($identityNo);
        }

        if (strlen($identityNo) < 5) {
            throw new BelsisException('Geçersiz kimlik numarası. En az 5 haneli olmalıdır.');
        }

        return $identityNo;
    }

    private function findGensicilByTc(string $tcKimlikNo): string
    {
        $tips = config('belsis.arama_sorgu_tips', ['TC', 'TcKimlikNo', '2']);
        $lastError = null;

        foreach ($tips as $tip) {
            try {
                $gensicil = $this->tryArama($tip, $tcKimlikNo);
                if ($gensicil !== null) {
                    return $gensicil;
                }
            } catch (BelsisException $e) {
                if ($this->isInfrastructureError($e)) {
                    throw $e;
                }
                $lastError = $e;
            }
        }

        foreach ($tips as $tip) {
            try {
                $gensicil = $this->tryBorcSorgulaByTc($tip, $tcKimlikNo);
                if ($gensicil !== null) {
                    return $gensicil;
                }
            } catch (BelsisException $e) {
                if ($this->isInfrastructureError($e)) {
                    throw $e;
                }
                $lastError = $e;
            }
        }

        if ($lastError !== null) {
            throw new BelsisException(
                'T.C. Kimlik No ile sicil bulunamadı. Belsis: '.$lastError->getMessage()
                .' — Sicil numaranızla deneyiniz veya belediye veznesine başvurunuz.',
                $lastError->sonucKodu,
            );
        }

        throw new BelsisException(
            'T.C. Kimlik No ile sicil bulunamadı. Sicil numaranızla deneyiniz veya belediyeye başvurunuz.',
        );
    }

    private function tryArama(string $sorguTip, string $tcKimlikNo): ?string
    {
        $result = $this->client->callTahsilat('arama', array_merge(
            $this->auth->baseParams(),
            ['sorguTip' => $sorguTip, 'sorguNo' => $tcKimlikNo],
        ));

        return $this->extractGensicilFromArama($result);
    }

    private function tryBorcSorgulaByTc(string $sorguTip, string $tcKimlikNo): ?string
    {
        $result = $this->client->callTahsilat('borcSorgula', array_merge(
            $this->auth->baseParams(),
            [
                'sorguTip'            => $sorguTip,
                'sorguNo'             => $tcKimlikNo,
                'gensicilno'          => 0,
                'indirimliOdenecekMi' => 0,
                'indirimHakkiVarMi'   => 0,
            ],
        ));

        $sicil = $result['Sicil'] ?? $result['sicil'] ?? [];
        if (is_array($sicil)) {
            $fromSicil = $sicil['sicilNo'] ?? $sicil['gensicilno'] ?? $sicil['gensicilNo'] ?? null;
            if ($fromSicil) {
                return (string) $fromSicil;
            }
        }

        return $this->extractGensicilFromArama($result);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function extractGensicilFromArama(array $result): ?string
    {
        $siciller = $result['Siciller'] ?? $result['siciller'] ?? null;

        if (is_array($siciller)) {
            $items = $siciller['SicilaramaObj'] ?? $siciller['sicilaramaObj'] ?? $siciller;
            foreach ($this->normalizeList($items) as $row) {
                $gensicil = $row['gensicilno'] ?? $row['gensicilNo'] ?? $row['Gensicilno'] ?? null;
                if ($gensicil) {
                    return (string) $gensicil;
                }
            }
        }

        $direct = $result['gensicilno'] ?? $result['gensicilNo'] ?? null;

        return $direct ? (string) $direct : null;
    }

    private function isInfrastructureError(BelsisException $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return str_contains($message, 'yetkisiz_ip')
            || str_contains($message, 'bağlanılamadı')
            || str_contains($message, 'baglanilamadi')
            || str_contains($message, 'html')
            || str_contains($message, 'oturum')
            || str_contains($message, 'ip adresini tanımıyor')
            || in_array($e->sonucKodu, ['401', '403', '1002', '1003'], true);
    }
}
