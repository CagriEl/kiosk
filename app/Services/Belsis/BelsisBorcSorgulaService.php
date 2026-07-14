<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use App\Services\Belsis\Concerns\ChecksBelsisInfrastructureErrors;
use App\Services\Belsis\Concerns\NormalizesBelsisLists;

class BelsisBorcSorgulaService
{
    use ChecksBelsisInfrastructureErrors;
    use NormalizesBelsisLists;

    public function __construct(
        private readonly BelsisSoapClient $client,
        private readonly BelsisAuthService $auth,
    ) {}

    /**
     * TC, abone no veya sicil no ile borç sorgusu — sorgu tipine göre farklı, doğrulamalı yol izlenir.
     *
     * @param  'tc'|'abone'|'sicil'|null  $searchType
     * @return array<string, mixed>
     */
    public function query(string $identityNo, ?string $searchType = null): array
    {
        $identityNo = trim($identityNo);

        if (! ctype_digit($identityNo)) {
            throw new BelsisException('Geçersiz abone numarası. Sadece rakam giriniz.');
        }

        $searchType = $this->resolveSearchType($identityNo, $searchType);

        if ($searchType === 'tc') {
            if (strlen($identityNo) !== 11) {
                throw new BelsisException('T.C. Kimlik No 11 haneli olmalıdır.');
            }

            return $this->queryByTc($identityNo);
        }

        if (strlen($identityNo) < 1) {
            throw new BelsisException('Abone numarası giriniz.');
        }

        if (strlen($identityNo) > 10) {
            throw new BelsisException('Abone numarası en fazla 10 haneli olabilir.');
        }

        if ($searchType === 'sicil') {
            return $this->querySicil($identityNo);
        }

        return $this->queryByAbone($identityNo);
    }

    /**
     * @param  'tc'|'abone'|'sicil'|null  $searchType
     */
    public function resolveGensicilNo(string $identityNo, ?string $searchType = null): string
    {
        $identityNo = trim($identityNo);

        if (! ctype_digit($identityNo)) {
            throw new BelsisException('Geçersiz abone numarası. Sadece rakam giriniz.');
        }

        $searchType = $this->resolveSearchType($identityNo, $searchType);

        if ($searchType === 'tc') {
            if (strlen($identityNo) !== 11) {
                throw new BelsisException('T.C. Kimlik No 11 haneli olmalıdır.');
            }

            // arama(TC) → gensicil, olmazsa borcSorgula(TC) → Sicil.sicilNo
            $gensicil = $this->resolveGensicilFromTc($identityNo);
            if ($gensicil !== null) {
                return $gensicil;
            }

            throw new BelsisException(
                'T.C. Kimlik No belediye kaydınızla eşleştirilemedi. Kayıtlarınızdaki TC güncel olmayabilir.',
            );
        }

        if (strlen($identityNo) < 1) {
            throw new BelsisException('Abone numarası giriniz.');
        }

        if ($searchType === 'sicil') {
            $gensicilInt = (int) $identityNo;
            if ($gensicilInt <= 0) {
                throw new BelsisException('Sicil numarası giriniz.');
            }

            if ($this->fetchSicilByGensicil($gensicilInt) !== null) {
                return $identityNo;
            }

            // Girilen sicil no gerçek gensicilno değilse (mükellef/abone no ile aynı
            // olabilir), mukellefNo/uyeNo eşleşmesi üzerinden de dener.
            $resolved = $this->resolveAboneRecord($identityNo);
            $resolvedGensicil = (int) ($resolved['gensicilno'] ?? $resolved['gensicilNo'] ?? 0);
            if ($resolvedGensicil > 0) {
                return (string) $resolvedGensicil;
            }

            throw new BelsisException('Sicil numarası belediye kaydınızla eşleştirilemedi.');
        }

        $this->queryByAbone($identityNo);

        return $this->resolveGensicilFromAbone($identityNo) ?? $identityNo;
    }

    /**
     * Sicil no ile borç sorgusu — önce gensicilno birebir denenir; bulunamazsa aynı numara
     * mukellefNo/uyeNo olarak da aranır (pickMatchingSicilRecord'daki birebir eşleşme
     * kontrolü sayesinde yanlış kişi riski olmadan).
     *
     * @return array<string, mixed>
     */
    private function querySicil(string $sicilNo): array
    {
        $gensicilInt = (int) $sicilNo;

        if ($gensicilInt <= 0) {
            throw new BelsisException('Sicil numarası giriniz.');
        }

        $record = $this->fetchSicilByGensicil($gensicilInt);
        $lastError = null;

        $result = $this->tryBorcSorgulaCombos($this->aboneBorcTips(), [$gensicilInt], [$sicilNo], $lastError);
        if ($result !== null) {
            return $result;
        }

        if ($record !== null) {
            return $this->buildFallbackBorcFromSicil($record);
        }

        // Girilen sicil no gerçek gensicilno değilse (mükellef/abone no ile aynı
        // olabilir), aynı motoru mukellefNo/uyeNo eşleşmesiyle de dener — yanlış kişi
        // riski pickMatchingSicilRecord'daki birebir eşleşme kontrolüyle önlenir.
        $resolved = $this->resolveAboneRecord($sicilNo);
        if ($resolved !== null) {
            $resolvedGensicil = (int) ($resolved['gensicilno'] ?? $resolved['gensicilNo'] ?? 0);

            if ($resolvedGensicil > 0) {
                $borc = $this->queryBorcByGensicil((string) $resolvedGensicil, $lastError, $sicilNo);
                if ($borc !== null) {
                    return $borc;
                }
            }

            return $this->buildFallbackBorcFromSicil($resolved);
        }

        throw new BelsisException(
            'Sicil numarası bulunamadı. Belsis: '.($lastError?->getMessage() ?? 'Kayıt yok')
            .' — Numarayı kontrol ediniz.',
            $lastError?->sonucKodu,
        );
    }

    /**
     * gensicilno ile doğrudan sicilSorgula — arama methoduna hiç başvurmadan kayıt doğrular.
     *
     * @return array<string, mixed>|null
     */
    private function fetchSicilByGensicil(int $gensicilNo): ?array
    {
        $record = $this->trySicilSorgula('tahsilat', ['gensicilno' => $gensicilNo, 'koyID' => 0], $gensicilNo);
        if ($record !== null) {
            return $record;
        }

        return $this->trySicilSorgula('tahakkuk', ['gensicilno' => $gensicilNo, 'koyID' => 0], $gensicilNo);
    }

    /**
     * borcSorgula (TC) yanıtından gensicilno — soft-success (1004) dahil kısmi yanıtlar.
     * Bir sorguTip CommandText verirse diğer tip adayları denenmeye devam eder.
     */
    public function resolveGensicilFromTcBorcResponse(string $tcKimlikNo): ?string
    {
        $tcKimlikNo = preg_replace('/\D/', '', trim($tcKimlikNo));

        if (strlen($tcKimlikNo) !== 11) {
            return null;
        }

        foreach ($this->tcBorcTips() as $tip) {
            try {
                $result = $this->callBorcSorgula($tip, $tcKimlikNo, 0);
                $fromBorc = $this->extractGensicilFromBorc($result);
                if ($fromBorc !== null) {
                    return $fromBorc;
                }
            } catch (BelsisException $e) {
                if ($this->isInfrastructureError($e)) {
                    throw $e;
                }
                // Bu tip SP'de yok / sistemik — sıradaki tip adayına geç
            }
        }

        return null;
    }

    /**
     * @param  'tc'|'abone'|'sicil'|null  $searchType
     * @return 'tc'|'abone'|'sicil'
     */
    private function resolveSearchType(string $identityNo, ?string $searchType): string
    {
        if (in_array($searchType, ['tc', 'abone', 'sicil'], true)) {
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
                $record = $this->tryAramaRecord($tip, $tcKimlikNo, true);
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
        $result = $this->tryBorcSorgulaCombos($this->tcBorcTips(), [0], [$tcKimlikNo], $lastError);
        if ($result !== null) {
            return $result;
        }

        throw new BelsisException(
            $this->formatTcNotFoundMessage($lastError),
            $lastError?->sonucKodu,
        );
    }

    private function formatTcNotFoundMessage(?BelsisException $lastError): string
    {
        if ($lastError?->sonucKodu === '1004') {
            return 'Belediye kaydınız bulundu ancak online tahsilatta görüntülenecek borç yok (Belsis: 1004). '
                .'T.C. Kimlik sicilinize tanımlı değilse belediye veznemize başvurunuz.';
        }

        return 'T.C. Kimlik No ile kayıt bulunamadı. Belsis: '
            .($lastError?->getMessage() ?? 'Sonuç boş')
            .' — Numarayı kontrol ediniz.';
    }

    /**
     * Çözümlenen gensicilno ile borcSorgula (sicil tipi).
     *
     * @return array<string, mixed>|null
     */
    private function queryBorcByGensicil(string $gensicilNo, ?BelsisException &$lastError = null, ?string $sorguNo = null): ?array
    {
        $gensicilInt = (int) $gensicilNo;
        $sorguNo = $sorguNo ?? $gensicilNo;

        return $this->tryBorcSorgulaCombos(
            $this->aboneBorcTips(),
            $this->gensicilnoCandidatesForAbone($gensicilInt),
            [$sorguNo],
            $lastError,
        );
    }

    /**
     * Abone no ile borç sorgusu (tahsilat: arama → borcSorgula, sicilSorgula uyeNo/mukellefNo eşleşmesi).
     *
     * @return array<string, mixed>
     */
    private function queryByAbone(string $aboneNo, bool $allowResolve = true): array
    {
        $aboneInt = (int) $aboneNo;
        $lastError = null;

        foreach ($this->aboneAramaTips() as $tip) {
            try {
                $record = $this->tryAramaRecord($tip, $aboneNo);
                if ($record === null) {
                    continue;
                }

                $gensicil = (string) ($record['gensicilno'] ?? $record['gensicilNo'] ?? '');
                if ($gensicil === '' || $gensicil === '0') {
                    continue;
                }

                $borc = $this->queryBorcByGensicil($gensicil, $lastError, $aboneNo);
                if ($borc !== null) {
                    return $borc;
                }

                $enriched = $this->resolveAboneRecord($aboneNo) ?? $record;

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

        $result = $this->tryBorcSorgulaCombos(
            $this->aboneBorcTips(),
            $this->gensicilnoCandidatesForAbone($aboneInt),
            [$aboneNo],
            $lastError,
        );
        if ($result !== null) {
            return $result;
        }

        if (! $allowResolve) {
            throw new BelsisException(
                'Abone numarası bulunamadı. Belsis: '.($lastError?->getMessage() ?? 'Kayıt yok')
                .' — Numarayı kontrol ediniz.',
                $lastError?->sonucKodu,
            );
        }

        if ($aboneRecord = $this->resolveAboneRecord($aboneNo)) {
            $resolvedGensicil = (int) ($aboneRecord['gensicilno'] ?? $aboneRecord['gensicilNo'] ?? 0);

            if ($resolvedGensicil > 0 && $resolvedGensicil !== $aboneInt) {
                $result = $this->tryBorcSorgulaCombos(
                    $this->aboneBorcTips(),
                    $this->gensicilnoCandidatesForAbone($resolvedGensicil),
                    [(string) $resolvedGensicil, $aboneNo],
                    $lastError,
                );
                if ($result !== null) {
                    return $result;
                }

                try {
                    return $this->queryByAbone((string) $resolvedGensicil, false);
                } catch (BelsisException $e) {
                    $lastError = $e;
                }
            }

            $tc = preg_replace('/\D/', '', (string) ($aboneRecord['tcKimlikNo'] ?? ''));
            if (strlen($tc) === 11) {
                $result = $this->tryBorcSorgulaCombos(
                    $this->tcBorcTips(),
                    [0, $resolvedGensicil > 0 ? $resolvedGensicil : $aboneInt],
                    [$tc],
                    $lastError,
                );
                if ($result !== null) {
                    return $result;
                }
            }

            return $this->buildFallbackBorcFromSicil($aboneRecord);
        }

        throw new BelsisException(
            'Abone numarası bulunamadı. Belsis: '.($lastError?->getMessage() ?? 'Kayıt yok')
            .' — Numarayı kontrol ediniz.',
            $lastError?->sonucKodu,
        );
    }

    /** @deprecated */
    private function queryBySicil(string $sicilNo, bool $allowResolve = true): array
    {
        return $this->queryByAbone($sicilNo, $allowResolve);
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

    /**
     * borcSorgula'yı verilen sorguTip x gensicilno x sorguNo kombinasyonlarıyla dener.
     * Bir sorguTip için "Sistem Hatası" (CommandText) alınırsa o tip için tüm kombinasyonlar
     * atlanır ve bir sonraki sorguTip'e geçilir — bu hata sorguTip'in değerinden değil, SP'nin
     * o tip için hiç çalışmamasından kaynaklanır (arama methodunda aynı hata sınıfı yüzünden
     * tüm sorguTip'ler başarısız olduğu için arama_enabled=false yapıldı, bkz. config/belsis.php).
     * Aynı boşuna round-trip'i borcSorgula için de önler.
     *
     * @param  array<int, string>  $tips
     * @param  array<int, int>  $gensicilCandidates
     * @param  array<int, string>  $sorguNos
     * @return array<string, mixed>|null
     */
    private function tryBorcSorgulaCombos(array $tips, array $gensicilCandidates, array $sorguNos, ?BelsisException &$lastError): ?array
    {
        foreach ($tips as $tip) {
            foreach ($gensicilCandidates as $gensicilno) {
                foreach ($sorguNos as $sorguNo) {
                    try {
                        $result = $this->callBorcSorgula($tip, $sorguNo, $gensicilno);
                        if ($this->isValidBorcResult($result)) {
                            return $result;
                        }

                        // Geçerli borç yoksa bile Sicil.sicilNo gelmiş olabilir (TC → gensicil çözümleme)
                        if ($this->extractGensicilFromBorc($result) !== null) {
                            return $result;
                        }
                    } catch (BelsisException $e) {
                        if ($this->isInfrastructureError($e)) {
                            throw $e;
                        }

                        $lastError = $e;

                        if ($this->isSystemicBorcError($e)) {
                            // Bu tip SP'de yok — kalan tip×gensicil kombolarını bu tip için atla
                            break 2;
                        }
                    }
                }
            }
        }

        return null;
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
    private function tryAramaRecord(string $sorguTip, string $sorguNo, bool $force = false): ?array
    {
        $matches = $this->tryAramaAllRecords($sorguTip, $sorguNo, $force);

        return $matches[0] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tryAramaAllRecords(string $sorguTip, string $sorguNo, bool $force = false): array
    {
        if (! $force && ! config('belsis.arama_enabled', false)) {
            return [];
        }

        try {
            $result = $this->client->callTahsilat('arama', array_merge(
                $this->auth->baseParams(),
                ['sorguTip' => $sorguTip, 'sorguNo' => $sorguNo],
            ));

            $siciller = $result['Siciller'] ?? $result['siciller'] ?? null;
            if (is_array($siciller)) {
                $items = $siciller['SicilaramaObj'] ?? $siciller['sicilaramaObj'] ?? $siciller;
                $candidates = $this->extractAramaCandidates($items);

                return $this->filterAramaCandidates($candidates, $sorguNo);
            }

            $direct = (int) ($result['gensicilno'] ?? $result['gensicilNo'] ?? 0);

            return $direct > 0 ? [['gensicilno' => $direct, 'adi' => '', 'soyadi' => '']] : [];
        } catch (BelsisException $e) {
            if ($this->isInfrastructureError($e)) {
                throw $e;
            }

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractAramaCandidates(mixed $items): array
    {
        $candidates = [];

        foreach ($this->normalizeList($items) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $gensicil = (int) ($row['gensicilno'] ?? $row['gensicilNo'] ?? $row['Gensicilno'] ?? 0);
            if ($gensicil > 0) {
                $candidates[] = [
                    'gensicilno' => $gensicil,
                    'adi'        => $row['adi'] ?? '',
                    'soyadi'     => $row['soyadi'] ?? '',
                    'tcKimlikNo' => preg_replace('/\D/', '', (string) ($row['tcKimlikNo'] ?? $row['TcKimlikNo'] ?? '')),
                    'uyeNo'      => (int) ($row['uyeNo'] ?? $row['UyeNo'] ?? 0),
                ];
            }
        }

        return $candidates;
    }

    /**
     * arama birden fazla aday döndürebilir. Tek aday → kabul.
     * TC (11 hane) → sicilSorgula.tcKimlikNo birebir eşleşen aday(lar).
     * Abone/sicil → uyeNo / gensicilno eşleşmesi.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    private function filterAramaCandidates(array $candidates, string $sorguNo): array
    {
        if ($candidates === []) {
            return [];
        }

        if (count($candidates) === 1) {
            return [$candidates[0]];
        }

        $sorguNo = trim($sorguNo);
        $isTc = strlen($sorguNo) === 11 && ctype_digit($sorguNo);
        $sorguInt = (int) $sorguNo;
        $matched = [];

        foreach ($candidates as $candidate) {
            if ($isTc) {
                $candidateTc = preg_replace('/\D/', '', (string) ($candidate['tcKimlikNo'] ?? ''));
                if ($candidateTc === $sorguNo) {
                    $matched[] = $candidate;
                    continue;
                }

                $record = $this->fetchSicilByGensicil((int) $candidate['gensicilno']);
                $recordTc = preg_replace('/\D/', '', (string) ($record['tcKimlikNo'] ?? ''));
                if ($recordTc === $sorguNo) {
                    $matched[] = $candidate;
                }

                continue;
            }

            if ((int) $candidate['gensicilno'] === $sorguInt
                || (int) ($candidate['uyeNo'] ?? 0) === $sorguInt) {
                $matched[] = $candidate;
                continue;
            }

            $record = $this->fetchSicilByGensicil((int) $candidate['gensicilno']);
            if ($record !== null && (int) ($record['uyeNo'] ?? -1) === $sorguInt) {
                $matched[] = $candidate;
            }
        }

        // TC ile birebir eşleşme bulunamadıysa yanlış kişi riski olmasın diye boş dön.
        // Tek aday zaten yukarıda kabul edildi.
        return $matched;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function pickAramaCandidate(array $candidates, string $sorguNo): ?array
    {
        $matched = $this->filterAramaCandidates($candidates, $sorguNo);

        return $matched[0] ?? null;
    }

    /**
     * Girilen abone numarasından kayıt çözer (tahsilat/tahakkuk sicilSorgula uyeNo/mukellefNo + arama).
     *
     * @return array<string, mixed>|null
     */
    private function resolveAboneRecord(string $aboneNo): ?array
    {
        $aboneInt = (int) $aboneNo;

        foreach ($this->aboneSorgulaParamVariants($aboneNo, $aboneInt) as $params) {
            $record = $this->trySicilSorgula('tahsilat', $params, $aboneInt);
            if ($record !== null) {
                return $record;
            }

            $record = $this->trySicilSorgula('tahakkuk', $params, $aboneInt);
            if ($record !== null) {
                return $record;
            }
        }

        return $this->tryResolveAboneViaArama($aboneNo);
    }

    /** @deprecated */
    private function resolveSicilRecord(string $sicilNo): ?array
    {
        return $this->resolveAboneRecord($sicilNo);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function aboneSorgulaParamVariants(string $aboneNo, int $aboneInt): array
    {
        return [
            ['gensicilno' => 0, 'koyID' => 0, 'mukellefNo' => $aboneNo],
            ['gensicilno' => $aboneInt, 'koyID' => 0, 'mukellefNo' => $aboneNo],
            ['gensicilno' => $aboneInt, 'koyID' => 0],
        ];
    }

    /** @deprecated */
    private function sicilSorgulaParamVariants(string $sicilNo, int $sicilInt): array
    {
        return $this->aboneSorgulaParamVariants($sicilNo, $sicilInt);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    private function trySicilSorgula(string $service, array $params, int $matchNo): ?array
    {
        try {
            $result = $service === 'tahakkuk'
                ? $this->client->callTahakkuk('sicilSorgula', array_merge($this->auth->baseParamsTahakkuk(), $params))
                : $this->client->callTahsilat('sicilSorgula', array_merge($this->auth->baseParams(), $params));

            $siciller = $this->normalizeList($result['sicilListesi']['sicilAlanlari'] ?? $result['sicilListesi'] ?? []);

            return $this->pickMatchingSicilRecord($siciller, $matchNo) ?? ($siciller[0] ?? null);
        } catch (BelsisException $e) {
            if ($this->isInfrastructureError($e)) {
                throw $e;
            }

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
    private function tryResolveAboneViaArama(string $aboneNo): ?array
    {
        if (! config('belsis.arama_enabled', false)) {
            return null;
        }

        foreach ($this->aboneAramaTips() as $tip) {
            try {
                $result = $this->client->callTahsilat('arama', array_merge(
                    $this->auth->baseParams(),
                    ['sorguTip' => $tip, 'sorguNo' => $aboneNo],
                ));

                $siciller = $result['Siciller'] ?? $result['siciller'] ?? null;
                if (! is_array($siciller)) {
                    continue;
                }

                $items = $siciller['SicilaramaObj'] ?? $siciller['sicilaramaObj'] ?? $siciller;
                $match = $this->pickAramaCandidate($this->extractAramaCandidates($items), $aboneNo);
                if ($match !== null) {
                    return $match;
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
        if (is_array($sicil)) {
            $no = $sicil['sicilNo']
                ?? $sicil['gensicilno']
                ?? $sicil['gensicilNo']
                ?? $sicil['Gensicilno']
                ?? null;

            if ($no !== null && $no !== '') {
                $parsed = (int) $no;
                if ($parsed > 0) {
                    return (string) $parsed;
                }
            }
        }

        $direct = (int) ($result['gensicilno'] ?? $result['gensicilNo'] ?? $result['sicilNo'] ?? 0);

        return $direct > 0 ? (string) $direct : null;
    }

    /**
     * TC'ye bağlı tüm gensicilno adaylarını döner (tekil öncelik sırası korunur).
     *
     * @return array<int, string>
     */
    public function resolveAllGensicilsFromTc(string $tcKimlikNo): array
    {
        $tcKimlikNo = preg_replace('/\D/', '', trim($tcKimlikNo));
        if (strlen($tcKimlikNo) !== 11) {
            return [];
        }

        $found = [];

        foreach ($this->tcAramaTips() as $tip) {
            foreach ($this->tryAramaAllRecords($tip, $tcKimlikNo, true) as $record) {
                $gensicil = (int) ($record['gensicilno'] ?? $record['gensicilNo'] ?? 0);
                if ($gensicil > 0) {
                    $found[(string) $gensicil] = (string) $gensicil;
                }
            }
        }

        $fromBorc = $this->resolveGensicilFromTcBorcResponse($tcKimlikNo);
        if ($fromBorc !== null) {
            $found[$fromBorc] = $fromBorc;
        }

        return array_values($found);
    }

    /**
     * TC → gensicilno çözümler.
     * 1) arama(sorguTip=TC) — Kırklareli için bilinçli olarak arama_enabled bayrağından bağımsız denenir
     * 2) borcSorgula(TC, gensicilno=0) yanıtındaki Sicil.sicilNo
     */
    public function resolveGensicilFromTc(string $tcKimlikNo): ?string
    {
        $all = $this->resolveAllGensicilsFromTc($tcKimlikNo);

        return $all[0] ?? null;
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
            foreach ($this->aboneAramaTips() as $tip) {
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

            if ($record = $this->resolveAboneRecord($identityNo)) {
                $resolved = (int) ($record['gensicilno'] ?? $record['gensicilNo'] ?? 0);
                if ($resolved > 0) {
                    return (string) $resolved;
                }
            }

            return $identityNo;
        }

        throw new BelsisException('Abone numarası ile kayıt çözümlenemedi.');
    }

    /**
     * Abone numarasından gensicilno çıkarır (borc yanıtı varsa önce onu kullanır).
     */
    public function resolveGensicilFromAbone(string $aboneNo, ?array $borcResult = null): ?string
    {
        $aboneNo = trim($aboneNo);

        if ($aboneNo === '' || ! ctype_digit($aboneNo)) {
            return null;
        }

        if (is_array($borcResult)) {
            $fromBorc = $this->extractGensicilFromBorc($borcResult);
            if ($fromBorc !== null) {
                return $fromBorc;
            }
        }

        try {
            return $this->extractSicilNo($borcResult ?? $this->query($aboneNo, 'abone'), $aboneNo);
        } catch (BelsisException $e) {
            if ($this->isInfrastructureError($e)) {
                throw $e;
            }

            foreach ($this->aboneAramaTips() as $tip) {
                try {
                    $gensicil = $this->tryArama($tip, $aboneNo);
                    if ($gensicil !== null && (int) $gensicil > 0) {
                        return $gensicil;
                    }
                } catch (BelsisException $inner) {
                    if ($this->isInfrastructureError($inner)) {
                        throw $inner;
                    }
                }
            }

            if ($record = $this->resolveAboneRecord($aboneNo)) {
                $resolved = (int) ($record['gensicilno'] ?? $record['gensicilNo'] ?? 0);
                if ($resolved > 0) {
                    return (string) $resolved;
                }
            }

            return strlen($aboneNo) <= 10 ? $aboneNo : null;
        }
    }

    /** @deprecated */
    public function resolveGensicilFromAccount(string $accountNo, ?array $borcResult = null): ?string
    {
        return $this->resolveGensicilFromAbone($accountNo, $borcResult);
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
    private function aboneBorcTips(): array
    {
        return $this->filterUnsafeBorcTips($this->uniqueTips(
            config('belsis.borc_sorgu_tips_abone', config('belsis.borc_sorgu_tips_sicil')),
            [],
            [config('belsis.borc_sorgu_tip_abone', '1'), 'UYE', 'UYENO', 'ABONE', 'MUKELLEF'],
        ));
    }

    /** @deprecated */
    private function sicilBorcTips(): array
    {
        return $this->aboneBorcTips();
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
    private function aboneAramaTips(): array
    {
        return $this->uniqueTips(
            config('belsis.arama_sorgu_tips_abone', config('belsis.arama_sorgu_tips_sicil')),
            config('belsis.borc_sorgu_tips_abone', config('belsis.borc_sorgu_tips_sicil')),
            ['UYE', 'UYENO', 'ABONE', 'MUKELLEF', '1'],
        );
    }

    /** @deprecated */
    private function sicilAramaTips(): array
    {
        return $this->aboneAramaTips();
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
    private function gensicilnoCandidatesForAbone(int $abone): array
    {
        return array_values(array_unique([$abone, 0]));
    }

    /** @deprecated */
    private function gensicilnoCandidatesForSicil(int $sicil): array
    {
        return $this->gensicilnoCandidatesForAbone($sicil);
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
            || str_contains($message, 'sistem hatas')
            || $e->sonucKodu === '1004';
    }

}
