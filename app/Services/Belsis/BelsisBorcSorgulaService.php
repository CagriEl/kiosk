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

        // Webservis akışı: önce arama(TC) → gensicilno, sonra borcSorgula(sicil tipi)
        foreach ($this->tcAramaTips() as $tip) {
            try {
                $record = $this->tryAramaRecord($tip, $tcKimlikNo);
                if ($record === null) {
                    continue;
                }

                $gensicil = (string) ($record['gensicilno'] ?? $record['gensicilNo'] ?? '');
                if ($gensicil === '' || $gensicil === '0') {
                    continue;
                }

                $borc = $this->queryBorcByGensicil($gensicil, $lastError);
                if ($borc !== null) {
                    return $borc;
                }

                $enriched = $this->resolveSicilRecord($gensicil) ?? $record;

                return $this->buildFallbackBorcFromSicil($enriched);
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

        // Doğrudan borcSorgula (TC tipi, gensicilno=0)
        foreach ($this->tcBorcTips() as $tip) {
            try {
                $result = $this->callBorcSorgula($tip, $tcKimlikNo, 0);
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

        throw new BelsisException(
            'T.C. Kimlik No ile kayıt bulunamadı. Belsis: '.($lastError?->getMessage() ?? 'Sonuç boş')
            .' — Numarayı kontrol ediniz.',
            $lastError?->sonucKodu,
        );
    }

    /**
     * Çözümlenen gensicilno ile borcSorgula (sicil tipi).
     *
     * @return array<string, mixed>|null
     */
    private function queryBorcByGensicil(string $gensicilNo, ?BelsisException &$lastError = null): ?array
    {
        $gensicilInt = (int) $gensicilNo;

        foreach ($this->sicilBorcTips() as $tip) {
            foreach ($this->gensicilnoCandidatesForSicil($gensicilInt) as $gensicilno) {
                try {
                    $result = $this->callBorcSorgula($tip, $gensicilNo, $gensicilno);
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

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function queryBySicil(string $sicilNo, bool $allowResolve = true): array
    {
        $sicilInt = (int) $sicilNo;
        $lastError = null;

        foreach ($this->sicilBorcTips() as $tip) {
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
                foreach ($this->sicilBorcTips() as $tip) {
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
                foreach ($this->tcBorcTips() as $tip) {
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
        $record = $this->tryAramaRecord($sorguTip, $sorguNo);
        if ($record === null) {
            return null;
        }

        $gensicil = $record['gensicilno'] ?? $record['gensicilNo'] ?? null;

        return $gensicil ? (string) $gensicil : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tryAramaRecord(string $sorguTip, string $sorguNo): ?array
    {
        try {
            $result = $this->client->callTahsilat('arama', array_merge(
                $this->auth->baseParams(),
                ['sorguTip' => $sorguTip, 'sorguNo' => $sorguNo],
            ));

            $siciller = $result['Siciller'] ?? $result['siciller'] ?? null;
            if (is_array($siciller)) {
                $items = $siciller['SicilaramaObj'] ?? $siciller['sicilaramaObj'] ?? $siciller;
                foreach ($this->normalizeList($items) as $row) {
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
            }

            $direct = (int) ($result['gensicilno'] ?? $result['gensicilNo'] ?? 0);

            return $direct > 0 ? ['gensicilno' => $direct, 'adi' => '', 'soyadi' => ''] : null;
        } catch (BelsisException $e) {
            if ($this->isInfrastructureError($e)) {
                throw $e;
            }

            return null;
        }
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

        if ($no === null || $no === '') {
            return null;
        }

        $parsed = (int) $no;

        return $parsed > 0 ? (string) $parsed : null;
    }

    /**
     * TC ile arama methodundan gensicilno çözer (borcSorgula öncesi webservis adımı).
     */
    public function resolveGensicilFromTc(string $tcKimlikNo): ?string
    {
        $tcKimlikNo = preg_replace('/\D/', '', trim($tcKimlikNo));

        if (strlen($tcKimlikNo) !== 11) {
            return null;
        }

        foreach ($this->tcAramaTips() as $tip) {
            $record = $this->tryAramaRecord($tip, $tcKimlikNo);
            if ($record === null) {
                continue;
            }

            $gensicil = (int) ($record['gensicilno'] ?? $record['gensicilNo'] ?? 0);
            if ($gensicil > 0) {
                return (string) $gensicil;
            }
        }

        return null;
    }

    /**
     * borcSorgula yanıtından veya arama ile gensicilno çıkarır.
     */
    public function extractSicilNo(array $borcResult, string $identityNo): string
    {
        $fromBorc = $this->extractGensicilFromBorc($borcResult);
        if ($fromBorc !== null) {
            return $fromBorc;
        }

        $identityNo = trim($identityNo);

        if (strlen($identityNo) === 11 && ctype_digit($identityNo)) {
            $fromTc = $this->resolveGensicilFromTc($identityNo);
            if ($fromTc !== null) {
                return $fromTc;
            }
        }

        if (strlen($identityNo) >= 1 && ctype_digit($identityNo) && strlen($identityNo) !== 11) {
            foreach ($this->sicilAramaTips() as $tip) {
                try {
                    $gensicil = $this->tryArama($tip, $identityNo);
                    if ($gensicil !== null && (int) $gensicil > 0) {
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

        throw new BelsisException('T.C. Kimlik No ile sicil numarası çözümlenemedi.');
    }

    /**
     * @return array<int, string>
     */
    private function tcAramaTips(): array
    {
        return $this->uniqueTips(
            config('belsis.arama_sorgu_tips'),
            [],
            ['2', 'TC', 'TcKimlikNo', 'TCKIMLIK', 'Tc'],
        );
    }

    /**
     * @return array<int, string>
     */
    private function tcBorcTips(): array
    {
        return $this->filterUnsafeBorcTips($this->uniqueTips(
            config('belsis.borc_sorgu_tips_tc'),
            [],
            [config('belsis.borc_sorgu_tip_tc', '2'), 'TC', 'TcKimlikNo', 'TCKIMLIK'],
        ));
    }

    /**
     * @return array<int, string>
     */
    private function sicilBorcTips(): array
    {
        return $this->filterUnsafeBorcTips($this->uniqueTips(
            config('belsis.borc_sorgu_tips_sicil'),
            [],
            [config('belsis.borc_sorgu_tip_sicil', '1'), 'SICIL', 'GENSICIL', 'Sicil'],
        ));
    }

    /**
     * borcSorgula için geçersiz sorguTip değerlerini çıkarır (CommandText hatası önlenir).
     *
     * @param  array<int, string>  $tips
     * @return array<int, string>
     */
    private function filterUnsafeBorcTips(array $tips): array
    {
        return array_values(array_filter(
            $tips,
            fn (string $tip) => $tip !== '' && ! in_array($tip, ['0'], true),
        ));
    }

    /**
     * @return array<int, string>
     */
    private function sicilAramaTips(): array
    {
        return $this->uniqueTips(
            config('belsis.arama_sorgu_tips_sicil'),
            config('belsis.borc_sorgu_tips_sicil'),
            ['SICIL', 'GENSICIL', 'UYE', 'UYENO', 'MUKELLEF', 'Sicil', '1'],
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
            || str_contains($message, 'sonuc bos')
            || str_contains($message, 'commandtext')
            || str_contains($message, 'command text')
            || str_contains($message, 'not been initialized')
            || str_contains($message, 'sistem hatas');
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
