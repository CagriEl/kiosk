<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use App\Services\Belsis\Concerns\NormalizesBelsisLists;

class BelsisBorcSorgulaService
{
    use NormalizesBelsisLists;

    public function __construct(
        private readonly BelsisSoapClient $client,
        private readonly BelsisAuthService $auth,
    ) {}

    /**
     * TC veya sicil ile borç sorgusu — tüm sorguTip kombinasyonları denenir.
     *
     * @param  'tc'|'sicil'|null  $searchType
     * @return array<string, mixed>
     */
    public function query(string $identityNo, ?string $searchType = null): array
    {
        $identityNo = trim($identityNo);

        if (! ctype_digit($identityNo)) {
            throw new BelsisException('Geçersiz kimlik numarası. Sadece rakam giriniz.');
        }

        $searchType = $this->resolveSearchType($identityNo, $searchType);

        if ($searchType === 'tc') {
            if (strlen($identityNo) !== 11) {
                throw new BelsisException('T.C. Kimlik No 11 haneli olmalıdır.');
            }

            return $this->queryByTc($identityNo);
        }

        if (strlen($identityNo) < 1) {
            throw new BelsisException('Sicil numarası giriniz.');
        }

        if (strlen($identityNo) > 10) {
            throw new BelsisException('Sicil numarası en fazla 10 haneli olabilir.');
        }

        return $this->queryBySicil($identityNo);
    }

    /**
     * @param  'tc'|'sicil'|null  $searchType
     */
    public function resolveGensicilNo(string $identityNo, ?string $searchType = null): string
    {
        $identityNo = trim($identityNo);

        if (! ctype_digit($identityNo)) {
            throw new BelsisException('Geçersiz kimlik numarası. Sadece rakam giriniz.');
        }

        $searchType = $this->resolveSearchType($identityNo, $searchType);

        if ($searchType === 'tc') {
            if (strlen($identityNo) !== 11) {
                throw new BelsisException('T.C. Kimlik No 11 haneli olmalıdır.');
            }

            return $this->extractSicilNo($this->queryByTc($identityNo), $identityNo);
        }

        if (strlen($identityNo) < 1) {
            throw new BelsisException('Sicil numarası giriniz.');
        }

        $this->queryBySicil($identityNo);

        return $identityNo;
    }

    /**
     * @param  'tc'|'sicil'|null  $searchType
     * @return 'tc'|'sicil'
     */
    private function resolveSearchType(string $identityNo, ?string $searchType): string
    {
        if (in_array($searchType, ['tc', 'sicil'], true)) {
            return $searchType;
        }

        return strlen($identityNo) === 11 ? 'tc' : 'sicil';
    }

    /**
     * @return array<string, mixed>
     */
    private function queryByTc(string $tcKimlikNo): array
    {
        $lastError = null;

        foreach ($this->tcTips() as $tip) {
            foreach ($this->gensicilnoCandidatesForTc() as $gensicilno) {
                try {
                    $result = $this->callBorcSorgula($tip, $tcKimlikNo, $gensicilno);
                    if ($this->isValidBorcResult($result)) {
                        return $result;
                    }
                } catch (BelsisException $e) {
                    if ($this->isInfrastructureError($e)) {
                        throw $e;
                    }
                    if ($this->isRecoverableBorcError($e)) {
                        $lastError = $e;
                        continue;
                    }
                    $lastError = $e;
                }
            }
        }

        $gensicil = null;
        foreach ($this->tcTips() as $tip) {
            try {
                $gensicil = $this->tryArama($tip, $tcKimlikNo);
                if ($gensicil !== null) {
                    break;
                }
            } catch (BelsisException $e) {
                if ($this->isInfrastructureError($e)) {
                    throw $e;
                }
                $lastError = $e;
            }
        }

        if ($gensicil !== null) {
            try {
                return $this->queryBySicil($gensicil);
            } catch (BelsisException $e) {
                $lastError = $e;
            }
        }

        throw new BelsisException(
            'T.C. Kimlik No ile kayıt bulunamadı. Belsis: '.($lastError?->getMessage() ?? 'Sonuç boş')
            .' — Sicil numaranızla deneyiniz.',
            $lastError?->sonucKodu,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function queryBySicil(string $sicilNo, bool $allowResolve = true): array
    {
        $sicilInt = (int) $sicilNo;
        $lastError = null;

        foreach ($this->sicilTips() as $tip) {
            foreach ($this->gensicilnoCandidatesForSicil($sicilInt) as $gensicilno) {
                try {
                    $result = $this->callBorcSorgula($tip, $sicilNo, $gensicilno);
                    if ($this->isValidBorcResult($result)) {
                        return $result;
                    }
                } catch (BelsisException $e) {
                    if ($this->isInfrastructureError($e)) {
                        throw $e;
                    }
                    if ($this->isRecoverableBorcError($e)) {
                        $lastError = $e;
                        continue;
                    }
                    $lastError = $e;
                }
            }
        }

        if (! $allowResolve) {
            throw new BelsisException(
                'Sicil numarası bulunamadı. Belsis: '.($lastError?->getMessage() ?? 'Kayıt yok')
                .' — Numarayı kontrol ediniz.',
                $lastError?->sonucKodu,
            );
        }

        if ($sicilRecord = $this->resolveSicilRecord($sicilNo)) {
            $resolvedGensicil = (int) ($sicilRecord['gensicilno'] ?? $sicilRecord['gensicilNo'] ?? 0);

            if ($resolvedGensicil > 0 && $resolvedGensicil !== $sicilInt) {
                foreach ($this->sicilTips() as $tip) {
                    foreach ($this->gensicilnoCandidatesForSicil($resolvedGensicil) as $gensicilno) {
                        foreach ([(string) $resolvedGensicil, $sicilNo] as $sorguNo) {
                            try {
                                $result = $this->callBorcSorgula($tip, $sorguNo, $gensicilno);
                                if ($this->isValidBorcResult($result)) {
                                    return $result;
                                }
                            } catch (BelsisException $e) {
                                if ($this->isInfrastructureError($e)) {
                                    throw $e;
                                }
                                $lastError = $e;
                            }
                        }
                    }
                }

                try {
                    return $this->queryBySicil((string) $resolvedGensicil, false);
                } catch (BelsisException $e) {
                    $lastError = $e;
                }
            }

            $tc = preg_replace('/\D/', '', (string) ($sicilRecord['tcKimlikNo'] ?? ''));
            if (strlen($tc) === 11) {
                foreach ($this->tcTips() as $tip) {
                    foreach ([0, $resolvedGensicil > 0 ? $resolvedGensicil : $sicilInt] as $gensicilno) {
                        try {
                            $result = $this->callBorcSorgula($tip, $tc, $gensicilno);
                            if ($this->isValidBorcResult($result)) {
                                return $result;
                            }
                        } catch (BelsisException $e) {
                            if ($this->isInfrastructureError($e)) {
                                throw $e;
                            }
                            $lastError = $e;
                        }
                    }
                }
            }

            return $this->buildFallbackBorcFromSicil($sicilRecord);
        }

        throw new BelsisException(
            'Sicil numarası bulunamadı. Belsis: '.($lastError?->getMessage() ?? 'Kayıt yok')
            .' — Numarayı kontrol ediniz.',
            $lastError?->sonucKodu,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function callBorcSorgula(string $sorguTip, string $sorguNo, int $gensicilno): array
    {
        return $this->client->callTahsilat('borcSorgula', array_merge(
            $this->auth->baseParams(),
            [
                'sorguTip'            => $sorguTip,
                'sorguNo'             => $sorguNo,
                'gensicilno'          => $gensicilno,
                'indirimliOdenecekMi' => 0,
                'indirimHakkiVarMi'   => 0,
            ],
        ));
    }

    private function tryArama(string $sorguTip, string $sorguNo): ?string
    {
        $result = $this->client->callTahsilat('arama', array_merge(
            $this->auth->baseParams(),
            ['sorguTip' => $sorguTip, 'sorguNo' => $sorguNo],
        ));

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

    /**
     * Girilen sicil/üye numarasından kayıt çözer (tahsilat/tahakkuk sicilSorgula + arama).
     *
     * @return array<string, mixed>|null
     */
    private function resolveSicilRecord(string $sicilNo): ?array
    {
        $sicilInt = (int) $sicilNo;

        foreach ($this->sicilSorgulaParamVariants($sicilNo, $sicilInt) as $params) {
            $record = $this->trySicilSorgula('tahsilat', $params, $sicilInt);
            if ($record !== null) {
                return $record;
            }

            $record = $this->trySicilSorgula('tahakkuk', $params, $sicilInt);
            if ($record !== null) {
                return $record;
            }
        }

        return $this->tryResolveSicilViaArama($sicilNo);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sicilSorgulaParamVariants(string $sicilNo, int $sicilInt): array
    {
        return [
            ['gensicilno' => $sicilInt, 'koyID' => 0, 'mukellefNo' => $sicilNo],
            ['gensicilno' => $sicilInt, 'koyID' => 0],
            ['gensicilno' => 0, 'koyID' => 0, 'mukellefNo' => $sicilNo],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    private function trySicilSorgula(string $service, array $params, int $matchNo): ?array
    {
        try {
            $result = $service === 'tahakkuk'
                ? $this->client->callTahakkuk('sicilSorgula', array_merge($this->auth->baseParams(), $params))
                : $this->client->callTahsilat('sicilSorgula', array_merge($this->auth->baseParams(), $params));

            $siciller = $this->normalizeList($result['sicilListesi']['sicilAlanlari'] ?? $result['sicilListesi'] ?? []);

            return $this->pickMatchingSicilRecord($siciller, $matchNo) ?? ($siciller[0] ?? null);
        } catch (BelsisException) {
            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $siciller
     * @return array<string, mixed>|null
     */
    private function pickMatchingSicilRecord(array $siciller, int $matchNo): ?array
    {
        foreach ($siciller as $row) {
            if (! is_array($row)) {
                continue;
            }

            $gensicil = (int) ($row['gensicilno'] ?? $row['gensicilNo'] ?? 0);
            $uyeNo = (int) ($row['uyeNo'] ?? 0);

            if ($gensicil === $matchNo || $uyeNo === $matchNo) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tryResolveSicilViaArama(string $sicilNo): ?array
    {
        foreach ($this->sicilAramaTips() as $tip) {
            try {
                $result = $this->client->callTahsilat('arama', array_merge(
                    $this->auth->baseParams(),
                    ['sorguTip' => $tip, 'sorguNo' => $sicilNo],
                ));

                $siciller = $result['Siciller'] ?? $result['siciller'] ?? null;
                if (! is_array($siciller)) {
                    continue;
                }

                $items = $this->normalizeList(
                    $siciller['SicilaramaObj'] ?? $siciller['sicilaramaObj'] ?? $siciller,
                );

                foreach ($items as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $gensicil = (int) ($row['gensicilno'] ?? $row['gensicilNo'] ?? $row['Gensicilno'] ?? 0);
                    if ($gensicil > 0) {
                        return [
                            'gensicilno' => $gensicil,
                            'adi'        => $row['adi'] ?? '',
                            'soyadi'     => $row['soyadi'] ?? '',
                        ];
                    }
                }
            } catch (BelsisException $e) {
                if ($this->isInfrastructureError($e)) {
                    throw $e;
                }
            }
        }

        return null;
    }

    /**
     * borcSorgula yanıt veremezse sicilSorgula kaydından minimum borcC üretir.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function buildFallbackBorcFromSicil(array $record): array
    {
        $gensicilno = (int) ($record['gensicilno'] ?? $record['gensicilNo'] ?? 0);
        $ad = trim((string) ($record['adi'] ?? ''));
        $soyad = trim((string) ($record['soyadi'] ?? ''));
        $unvan = trim((string) ($record['unvan'] ?? ''));
        $name = $unvan !== '' ? $unvan : trim($ad.' '.$soyad);

        return [
            'sonucKodu' => '1001',
            'Sicil'     => [
                'sicilNo'          => $gensicilno,
                'adiSoyadiUnvani'  => $name,
                'modulListesi'     => [],
            ],
            '_fallback' => 'sicilSorgula',
        ];
    }

    private function sicilExistsViaSicilSorgula(int $gensicilno): bool
    {
        return $this->resolveSicilRecord((string) $gensicilno) !== null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function isValidBorcResult(array $result): bool
    {
        $sicil = $result['Sicil'] ?? $result['sicil'] ?? null;
        if (! is_array($sicil)) {
            return false;
        }

        $sicilNo = (int) ($sicil['sicilNo'] ?? $sicil['gensicilno'] ?? $sicil['gensicilNo'] ?? 0);
        if ($sicilNo > 0) {
            return true;
        }

        if (trim((string) ($sicil['adiSoyadiUnvani'] ?? '')) !== '') {
            return true;
        }

        $moduller = $this->normalizeList($sicil['modulListesi']['Modul'] ?? $sicil['modulListesi'] ?? []);

        return ! empty($moduller);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function extractGensicilFromBorc(array $result): ?string
    {
        $sicil = $result['Sicil'] ?? $result['sicil'] ?? null;
        if (! is_array($sicil)) {
            return null;
        }

        $no = $sicil['sicilNo'] ?? $sicil['gensicilno'] ?? $sicil['gensicilNo'] ?? null;

        return $no ? (string) $no : null;
    }

    /**
     * borcSorgula yanıtından veya arama ile gensicilno çıkarır.
     */
    public function extractSicilNo(array $borcResult, string $identityNo): string
    {
        $fromBorc = $this->extractGensicilFromBorc($borcResult);
        if ($fromBorc !== null && $fromBorc !== '') {
            return $fromBorc;
        }

        $identityNo = trim($identityNo);

        if (strlen($identityNo) === 11 && ctype_digit($identityNo)) {
            foreach ($this->tcTips() as $tip) {
                try {
                    $gensicil = $this->tryArama($tip, $identityNo);
                    if ($gensicil !== null) {
                        return $gensicil;
                    }
                } catch (BelsisException $e) {
                    if ($this->isInfrastructureError($e)) {
                        throw $e;
                    }
                }
            }
        }

        if (strlen($identityNo) >= 1 && ctype_digit($identityNo) && strlen($identityNo) !== 11) {
            foreach ($this->sicilAramaTips() as $tip) {
                try {
                    $gensicil = $this->tryArama($tip, $identityNo);
                    if ($gensicil !== null) {
                        return $gensicil;
                    }
                } catch (BelsisException $e) {
                    if ($this->isInfrastructureError($e)) {
                        throw $e;
                    }
                }
            }

            if ($record = $this->resolveSicilRecord($identityNo)) {
                $resolved = (int) ($record['gensicilno'] ?? $record['gensicilNo'] ?? 0);
                if ($resolved > 0) {
                    return (string) $resolved;
                }
            }

            return $identityNo;
        }

        throw new BelsisException('Sicil numarası çözümlenemedi.');
    }

    /**
     * @return array<int, string>
     */
    private function tcTips(): array
    {
        return $this->uniqueTips(
            config('belsis.borc_sorgu_tips_tc'),
            config('belsis.arama_sorgu_tips', []),
            [config('belsis.borc_sorgu_tip_tc', 'TC')],
        );
    }

    /**
     * @return array<int, string>
     */
    private function sicilTips(): array
    {
        return $this->uniqueTips(
            config('belsis.borc_sorgu_tips_sicil'),
            config('belsis.arama_sorgu_tips_sicil', []),
            [config('belsis.borc_sorgu_tip_sicil', 'SICIL'), 'GENSICIL', 'UYE', 'UYENO', 'MUKELLEF', 'Sicil', '1', '2', '3', '0'],
        );
    }

    /**
     * @return array<int, string>
     */
    private function sicilAramaTips(): array
    {
        return $this->uniqueTips(
            config('belsis.arama_sorgu_tips_sicil'),
            config('belsis.borc_sorgu_tips_sicil'),
            ['SICIL', 'GENSICIL', 'UYE', 'UYENO', 'MUKELLEF', 'Sicil', '1', '2', '3', '0'],
        );
    }

    /**
     * @param  array<int, string>|string|null  $primary
     * @param  array<int, string>|string|null  $secondary
     * @param  array<int, string>  $fallback
     * @return array<int, string>
     */
    private function uniqueTips(array|string|null $primary, array|string|null $secondary, array $fallback): array
    {
        $tips = array_merge(
            $this->parseTips($primary),
            $this->parseTips($secondary),
            $fallback,
        );

        return array_values(array_unique(array_filter($tips, fn ($tip) => $tip !== '')));
    }

    /**
     * @return array<int, string>
     */
    private function parseTips(array|string|null $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        if (is_string($value) && $value !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        return [];
    }

    /**
     * @return array<int, int>
     */
    private function gensicilnoCandidatesForTc(): array
    {
        return [0];
    }

    /**
     * @return array<int, int>
     */
    private function gensicilnoCandidatesForSicil(int $sicil): array
    {
        return array_values(array_unique([$sicil, 0]));
    }

    private function isRecoverableBorcError(BelsisException $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return str_contains($message, 'kayıt yok')
            || str_contains($message, 'kayit yok')
            || str_contains($message, 'bulunamad')
            || str_contains($message, 'sicil')
            || str_contains($message, 'sorgutip')
            || str_contains($message, 'sorgu tip')
            || str_contains($message, 'parametre')
            || str_contains($message, 'eşleş')
            || str_contains($message, 'esles')
            || str_contains($message, 'sonuç boş')
            || str_contains($message, 'sonuc bos');
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
