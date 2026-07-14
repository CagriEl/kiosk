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

        // Kırklareli: sicilSorgula(mukellefNo=TC) → gensicil → borc
        foreach ($this->resolveSicilsByTcKimlikNo($tcKimlikNo) as $gensicil) {
            $borc = $this->queryBorcByGensicil($gensicil, $lastError);
            if ($borc !== null) {
                return $borc;
            }

            $record = $this->fetchSicilByGensicil((int) $gensicil);
            if ($record !== null) {
                return $this->buildFallbackBorcFromSicil($record);
            }
        }

        // Webservis akışı: arama(TC) → gensicilno, sonra borcSorgula(sicil tipi)
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
     * TC'ye bağlı tüm gensicilno adaylarını döner.
     *
     * @return array<int, string>
     */
    public function resolveAllGensicilsFromTc(string $tcKimlikNo): array
    {
        return array_values(array_unique(array_map(
            fn (array $account) => (string) $account['gensicilNo'],
            $this->resolveAccountsFromTc($tcKimlikNo),
        )));
    }

    /**
     * TC’ye bağlı abonelik/sicil kartları (çoklu seçim ekranı için).
     *
     * @return array<int, array{
     *   gensicilNo: string,
     *   sicilNo: string,
     *   aboneNo: string,
     *   uyeNo: string,
     *   fullName: string,
     *   adi: string,
     *   soyadi: string,
     *   address: string,
     *   koyAdi: string,
     *   details: array<int, string>
     * }>
     */
    public function resolveAccountsFromTc(string $tcKimlikNo): array
    {
        $tcKimlikNo = preg_replace('/\D/', '', trim($tcKimlikNo));
        if (strlen($tcKimlikNo) !== 11) {
            return [];
        }

        $rows = $this->fetchSicilRecordsByTcKimlikNo($tcKimlikNo);

        if ($rows === []) {
            foreach ($this->tcAramaTips() as $tip) {
                foreach ($this->tryAramaAllRecords($tip, $tcKimlikNo, true) as $record) {
                    $gensicil = (int) ($record['gensicilno'] ?? $record['gensicilNo'] ?? 0);
                    if ($gensicil <= 0) {
                        continue;
                    }
                    $enriched = $this->fetchSicilByGensicil($gensicil) ?? $record;
                    $rows[] = $enriched;
                }
            }
        }

        if ($rows === []) {
            $fromBorc = $this->resolveGensicilFromTcBorcResponse($tcKimlikNo);
            if ($fromBorc !== null) {
                $enriched = $this->fetchSicilByGensicil((int) $fromBorc);
                if ($enriched !== null) {
                    $rows[] = $enriched;
                } else {
                    $rows[] = ['gensicilno' => (int) $fromBorc, 'tcKimlikNo' => $tcKimlikNo];
                }
            }
        }

        $accounts = [];
        $seen = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $gensicil = (int) ($row['gensicilno'] ?? $row['gensicilNo'] ?? 0);
            if ($gensicil <= 0) {
                continue;
            }

            foreach ($this->expandAccountsFromSicilRow($row, $gensicil) as $account) {
                $key = $account['gensicilNo'].'|'.$account['aboneNo'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $accounts[] = $account;
            }
        }

        return $accounts;
    }

    /**
     * Tek sicil kaydını yalnızca su aboneliklerine göre kartlara böler.
     * Bina / emlak / ÇTV vb. beyanlar seçim ekranına gelmez.
     *
     * @param  array<string, mixed>  $row
     * @return array<int, array<string, mixed>>
     */
    private function expandAccountsFromSicilRow(array $row, int $gensicil): array
    {
        $beyan = $this->fetchWaterBeyanEntriesForGensicil($gensicil);

        if ($beyan['entries'] !== []) {
            $accounts = [];
            foreach ($beyan['entries'] as $entry) {
                // Kart tutarı: satır borçlarından abone filtreli odenecek toplamı
                // (beyan.toplamBorc bazen eski/eksik kalıyor)
                $sum = $this->sumDebtsForAbone($gensicil, (string) $entry['aboneNo']);
                if ($sum !== null) {
                    $entry['toplamBorc'] = $sum;
                }
                $accounts[] = $this->mapTcAccountCard($row, $gensicil, $entry);
            }

            return $accounts;
        }

        // Beyan geldi ama su yok (yalnızca bina vb.) — bu sicili seçime koyma
        if ($beyan['hadAnyBeyan']) {
            return [];
        }

        // Beyan yanıtı boş / alınamadı — tek kart (eski davranış)
        return [$this->mapTcAccountCard($row, $gensicil, null)];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{aboneNo: string, modulNo: string, beyanId: string, label: string, toplamBorc: float}|null  $beyanEntry
     * @return array{
     *   gensicilNo: string,
     *   sicilNo: string,
     *   aboneNo: string,
     *   uyeNo: string,
     *   fullName: string,
     *   adi: string,
     *   soyadi: string,
     *   address: string,
     *   koyAdi: string,
     *   details: array<int, string>,
     *   modulNo: string,
     *   beyanId: string,
     *   accountKey: string
     * }
     */
    private function mapTcAccountCard(array $row, int $gensicil, ?array $beyanEntry): array
    {
        $adi = trim((string) ($row['adi'] ?? ''));
        $soyadi = trim((string) ($row['soyadi'] ?? ''));
        $unvan = trim((string) ($row['unvan'] ?? ''));
        $uyeNo = (int) ($row['uyeNo'] ?? 0);
        $koyAdi = trim((string) ($row['koyAdi'] ?? ''));

        $aboneNo = $beyanEntry['aboneNo'] ?? '';
        if ($aboneNo === '') {
            $aboneNo = $uyeNo > 0 ? (string) $uyeNo : (string) $gensicil;
        }

        $label = trim((string) ($beyanEntry['label'] ?? ''));
        $details = array_values(array_filter([
            $label !== '' ? $label : null,
            $koyAdi !== '' ? ('Mahalle / Yer: '.$koyAdi) : null,
        ]));

        $addressParts = array_values(array_filter([
            $koyAdi !== '' ? $koyAdi : null,
            $label !== '' && ! preg_match('/^abone\s*no/iu', $label) ? $label : null,
        ]));

        if ($addressParts === [] && $label !== '') {
            $addressParts = [$label];
        }

        $totalDebt = round($this->parseMoney(is_array($beyanEntry) ? ($beyanEntry['toplamBorc'] ?? 0) : 0), 2);

        return [
            'gensicilNo' => (string) $gensicil,
            'sicilNo'    => (string) $gensicil,
            'aboneNo'    => $aboneNo,
            'uyeNo'      => $uyeNo > 0 ? (string) $uyeNo : '',
            'fullName'   => $unvan !== '' ? $unvan : (trim($adi.' '.$soyadi) ?: ('Sicil: '.$gensicil)),
            'adi'        => $adi,
            'soyadi'     => $soyadi,
            'address'    => $addressParts !== [] ? implode(' · ', $addressParts) : 'Adres bilgisi kayıtta yok',
            'koyAdi'     => $koyAdi,
            'details'    => $details,
            'modulNo'    => (string) ($beyanEntry['modulNo'] ?? ''),
            'beyanId'    => (string) ($beyanEntry['beyanId'] ?? ''),
            'totalDebt'  => $totalDebt,
            'accountKey' => $gensicil.'|'.$aboneNo,
        ];
    }

    /**
     * @return array{
     *   entries: array<int, array{aboneNo: string, modulNo: string, beyanId: string, label: string, toplamBorc: float}>,
     *   hadAnyBeyan: bool
     * }
     */
    private function fetchWaterBeyanEntriesForGensicil(int $gensicil): array
    {
        try {
            $result = $this->client->callTahsilat('sicilBorcBeyanSorgula', array_merge(
                $this->auth->baseParams(),
                ['gensicilno' => $gensicil],
            ));

            $items = $this->normalizeList(
                $result['sicilBorcBeyanListesi']['sicilBorcBeyanListesi']
                ?? $result['sicilBorcBeyanListesi']
                ?? [],
            );

            $entries = [];
            $seenAbone = [];
            $hadAnyBeyan = false;

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $label = trim((string) ($item['beyanAciklama'] ?? ''));
                $beyanId = trim((string) ($item['beyanID'] ?? $item['beyanId'] ?? ''));
                $modulNo = trim((string) ($item['modulno'] ?? $item['modulNo'] ?? ''));

                if ($label === '' || str_contains(mb_strtoupper($label), 'HEPSİ')) {
                    continue;
                }

                $hadAnyBeyan = true;

                if (! $this->isWaterSubscriptionBeyan($label, $modulNo, $beyanId)) {
                    continue;
                }

                $aboneNo = '';
                if (preg_match('/abone\s*no\s*[:\.]?\s*(\d+)/iu', $label, $m)) {
                    $aboneNo = $m[1];
                } elseif (preg_match('/^(\d+)\|(\d+)$/', $beyanId, $m) && $m[2] !== '0') {
                    $aboneNo = $m[2];
                    if ($modulNo === '') {
                        $modulNo = $m[1];
                    }
                }

                if ($aboneNo === '') {
                    continue;
                }

                if (isset($seenAbone[$aboneNo])) {
                    $idx = $seenAbone[$aboneNo];
                    $entries[$idx]['toplamBorc'] += $this->parseMoney($item['toplamBorc'] ?? 0);
                    continue;
                }
                $seenAbone[$aboneNo] = count($entries);

                $entries[] = [
                    'aboneNo'    => $aboneNo,
                    'modulNo'    => $modulNo,
                    'beyanId'    => $beyanId,
                    'label'      => $label,
                    'toplamBorc' => $this->parseMoney($item['toplamBorc'] ?? 0),
                ];
            }

            return [
                'entries'     => $entries,
                'hadAnyBeyan' => $hadAnyBeyan,
            ];
        } catch (BelsisException $e) {
            if ($this->isInfrastructureError($e)) {
                throw $e;
            }

            return [
                'entries'     => [],
                'hadAnyBeyan' => false,
            ];
        }
    }

    /**
     * Seçilen abone için online odenecekTutar toplamı.
     * beyanBilgisi örn. "KATI ATIK TAHAKKUK - 41911 ..." ile abone eşleşir.
     */
    private function sumDebtsForAbone(int $gensicil, string $aboneNo): ?float
    {
        $aboneNo = trim($aboneNo);
        if ($aboneNo === '') {
            return null;
        }

        $tips = config('belsis.borc_sorgu_tips_gensicil', ['1']);

        foreach ($tips as $tip) {
            try {
                $result = $this->client->callTahsilat('borcSorgula', array_merge(
                    $this->auth->baseParams(),
                    [
                        'sorguTip'            => $tip,
                        'sorguNo'             => (string) $gensicil,
                        'gensicilno'          => $gensicil,
                        'indirimliOdenecekMi' => 0,
                        'indirimHakkiVarMi'   => 0,
                    ],
                ));

                $sicil = $result['Sicil'] ?? $result['sicil'] ?? null;
                if (! is_array($sicil)) {
                    continue;
                }

                $sicilNo = (int) ($sicil['sicilNo'] ?? $sicil['gensicilno'] ?? $sicil['gensicilNo'] ?? 0);
                if ($sicilNo !== $gensicil) {
                    continue;
                }

                $sum = 0.0;
                $found = false;
                $moduls = $this->normalizeList($sicil['modulListesi']['Modul'] ?? $sicil['modulListesi'] ?? []);

                foreach ($moduls as $modul) {
                    if (! is_array($modul)) {
                        continue;
                    }

                    $donems = $this->normalizeList($modul['donemListesi']['Donem'] ?? $modul['donemListesi'] ?? []);
                    foreach ($donems as $donem) {
                        if (! is_array($donem)) {
                            continue;
                        }
                        $tahs = $this->normalizeList($donem['tahakkukListesi']['Tahakkuk'] ?? $donem['tahakkukListesi'] ?? []);
                        foreach ($tahs as $tahakkuk) {
                            if (! is_array($tahakkuk)) {
                                continue;
                            }

                            $beyanBilgisi = (string) ($tahakkuk['beyanBilgisi'] ?? '');
                            $label = (string) ($tahakkuk['turu'] ?? '');
                            $beyanId = (string) ($tahakkuk['beyanID'] ?? '');
                            $itemAbone = $this->extractAboneNoFromBeyanText(implode(' ', [$beyanBilgisi, $label, $beyanId]));
                            if ($itemAbone !== $aboneNo) {
                                continue;
                            }

                            $amount = $this->resolveOdenecekFromTahakkuk($tahakkuk);
                            if ($amount <= 0) {
                                continue;
                            }

                            $sum += $amount;
                            $found = true;
                        }
                    }
                }

                if ($found) {
                    return round($sum, 2);
                }
            } catch (BelsisException $e) {
                if ($this->isInfrastructureError($e)) {
                    throw $e;
                }
            }
        }

        return null;
    }

    private function extractAboneNoFromBeyanText(string $text): string
    {
        if (preg_match('/abone\s*no\s*[:\.]?\s*(\d+)/iu', $text, $m)) {
            return $m[1];
        }

        if (preg_match('/tahakkuk\s*-\s*(\d+)/iu', $text, $m)) {
            return $m[1];
        }

        if (preg_match('/^(\d+)\|(\d+)$/', trim($text), $m) && $m[2] !== '0') {
            return $m[2];
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $tahakkuk
     */
    private function resolveOdenecekFromTahakkuk(array $tahakkuk): float
    {
        $odenecek = $this->parseMoney($tahakkuk['odenecekTutar'] ?? null);
        if ($odenecek > 0) {
            return round($odenecek, 2);
        }

        $tahakkukTutari = $this->parseMoney($tahakkuk['tahakkukTutari'] ?? 0);
        $gecikme = $this->parseMoney($tahakkuk['gecikmeZammi'] ?? 0);
        $odenen = $this->parseMoney($tahakkuk['odenenTutar'] ?? 0);
        $indirim = $this->parseMoney($tahakkuk['indirimTutari'] ?? 0);

        return round(max(0, $tahakkukTutari + $gecikme - $odenen - $indirim), 2);
    }

    private function parseMoney(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }

        // "3.240,50" / "3240,50" / "3240.50"
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $raw)) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',') && ! str_contains($raw, '.')) {
            $raw = str_replace(',', '.', $raw);
        }

        return (float) $raw;
    }

    /**
     * Su aboneliği beyanı mı? (bina/emlak/ÇTV vb. hariç)
     */
    private function isWaterSubscriptionBeyan(string $label, string $modulNo, string $beyanId): bool
    {
        $combined = mb_strtolower(trim($label.' '.$modulNo.' '.$beyanId));

        if (preg_match(
            '/\b(bina|emlak|arsa|çtv|ctv|ilan|reklam|çevre\s*temizlik|cevre\s*temizlik|işyeri|isyeri|ruhsat|haciz|gelir)\b/u',
            $combined,
        ) && ! preg_match('/abone\s*no/u', $combined) && ! preg_match('/\bsu\b/u', $combined)) {
            return false;
        }

        if (preg_match('/abone\s*no/iu', $label)) {
            return true;
        }

        if (preg_match('/\b(su\s*abon|su\s*borc|içme\s*su|icme\s*su|\bsu\b)/u', $combined)) {
            return true;
        }

        $waterModules = array_map('strval', config('belsis.water_modul_nos', ['24']));

        if ($modulNo !== '' && in_array($modulNo, $waterModules, true)) {
            return true;
        }

        if (preg_match('/^(\d+)\|(\d+)$/', $beyanId, $m)
            && in_array($m[1], $waterModules, true)
            && $m[2] !== '0'
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function fetchBeyanLabelsForGensicil(int $gensicil): array
    {
        return array_values(array_map(
            fn (array $entry) => $entry['label'],
            $this->fetchWaterBeyanEntriesForGensicil($gensicil)['entries'],
        ));
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function extractAboneNoFromBeyanLabels(array $labels): string
    {
        foreach ($labels as $label) {
            if (preg_match('/abone\s*no\s*[:\.]?\s*(\d+)/iu', $label, $m)) {
                return $m[1];
            }
        }

        return '';
    }

    /**
     * sicilSorgula(mukellefNo=TC) — yalnızca tcKimlikNo birebir eşleşen kayıtlar.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchSicilRecordsByTcKimlikNo(string $tcKimlikNo): array
    {
        $paramSets = [
            ['gensicilno' => 0, 'koyID' => 0, 'mukellefNo' => $tcKimlikNo],
        ];

        $rows = [];

        foreach ($paramSets as $params) {
            foreach ($this->fetchSicilSorgulaRows('tahsilat', $params) as $row) {
                $rowTc = preg_replace('/\D/', '', (string) ($row['tcKimlikNo'] ?? ''));
                if ($rowTc === $tcKimlikNo) {
                    $rows[] = $row;
                }
            }
        }

        if ($rows !== []) {
            return $rows;
        }

        foreach ($paramSets as $params) {
            foreach ($this->fetchSicilSorgulaRows('tahakkuk', $params) as $row) {
                $rowTc = preg_replace('/\D/', '', (string) ($row['tcKimlikNo'] ?? ''));
                if ($rowTc === $tcKimlikNo) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * @deprecated use fetchSicilRecordsByTcKimlikNo
     *
     * @return array<int, string>
     */
    private function resolveSicilsByTcKimlikNo(string $tcKimlikNo): array
    {
        $found = [];
        foreach ($this->fetchSicilRecordsByTcKimlikNo($tcKimlikNo) as $row) {
            $gensicil = (int) ($row['gensicilno'] ?? $row['gensicilNo'] ?? 0);
            if ($gensicil > 0) {
                $found[(string) $gensicil] = (string) $gensicil;
            }
        }

        return array_values($found);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchSicilSorgulaRows(string $service, array $params): array
    {
        try {
            $result = $service === 'tahakkuk'
                ? $this->client->callTahakkuk('sicilSorgula', array_merge($this->auth->baseParamsTahakkuk(), $params))
                : $this->client->callTahsilat('sicilSorgula', array_merge($this->auth->baseParams(), $params));

            return $this->normalizeList($result['sicilListesi']['sicilAlanlari'] ?? $result['sicilListesi'] ?? []);
        } catch (BelsisException $e) {
            // Tahakkuk oturumu / geçici hatalar TC çözümünü tamamen bozmasın
            if ($service === 'tahakkuk') {
                return [];
            }

            if ($this->isInfrastructureError($e)) {
                throw $e;
            }

            return [];
        }
    }

    /**
     * TC → gensicilno çözümler.
     * 1) sicilSorgula(mukellefNo=TC)
     * 2) arama(TC)
     * 3) borcSorgula(TC)
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
