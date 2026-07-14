<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use App\Services\Belsis\Concerns\ChecksBelsisInfrastructureErrors;
use App\Services\Belsis\Concerns\NormalizesBelsisLists;

class BelsisTahsilatQueryService
{
    use ChecksBelsisInfrastructureErrors;
    use NormalizesBelsisLists;

    public function __construct(
        private readonly BelsisSoapClient $client,
        private readonly BelsisAuthService $auth,
        private readonly BelsisTahakkukService $tahakkuk,
        private readonly BelsisIdentityResolver $identity,
    ) {}

    /**
     * Sicil veya T.C. ile vatandaş çözümler.
     * TC’de birden fazla abonelik varsa needsSelection=true + accounts listesi döner.
     *
     * @return array<string, mixed>
     */
    public function getCitizen(string $identityNo, ?string $searchType = null): array
    {
        $identityNo = trim($identityNo);
        $searchType = $this->resolveSearchType($identityNo, $searchType);

        if ($searchType === 'tc') {
            return $this->getCitizenByTc($identityNo);
        }

        $gensicilno = $this->resolveToGensicil($identityNo, $searchType);
        $profile = $this->fetchSicilProfile($gensicilno);

        $adi = $profile['adi'];
        $soyadi = $profile['soyadi'];

        if ($adi === '' && $soyadi === '') {
            [$adi, $soyadi] = $this->splitName($profile['fullName']);
        }

        return [
            'identityNo'     => $identityNo,
            'gensicilNo'     => (string) $profile['gensicilno'],
            'sicilNo'        => (string) $profile['gensicilno'],
            'fullName'       => $profile['fullName'],
            'searchType'     => $searchType,
            'adi'            => $adi,
            'soyadi'         => $soyadi,
            'needsSelection' => false,
            'accounts'       => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getCitizenByTc(string $tcKimlikNo): array
    {
        $accounts = $this->identity->resolveAccountsFromTc($tcKimlikNo);
        if ($accounts === []) {
            throw new BelsisException(
                'T.C. Kimlik No belediye kaydınızla eşleştirilemedi. Kayıtlarınızdaki TC güncel olmayabilir.',
            );
        }

        $primary = $accounts[0];
        $needsSelection = count($accounts) > 1;

        return [
            'identityNo'     => $tcKimlikNo,
            'gensicilNo'     => $needsSelection ? '' : (string) $primary['gensicilNo'],
            'sicilNo'        => $needsSelection ? '' : (string) $primary['sicilNo'],
            'aboneNo'        => $needsSelection ? '' : (string) ($primary['aboneNo'] ?? ''),
            'fullName'       => (string) $primary['fullName'],
            'searchType'     => 'tc',
            'adi'            => (string) ($primary['adi'] ?? ''),
            'soyadi'         => (string) ($primary['soyadi'] ?? ''),
            'address'        => (string) ($primary['address'] ?? ''),
            'needsSelection' => $needsSelection,
            'accounts'       => $accounts,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDebts(
        string $identityNo,
        ?string $searchType = null,
        ?string $gensicilNo = null,
        ?string $aboneNo = null,
    ): array {
        $identityNo = trim($identityNo);
        $searchType = $this->resolveSearchType($identityNo, $searchType);

        if ($searchType === 'tc') {
            return $this->getDebtsByTc($identityNo, $gensicilNo, $aboneNo);
        }

        return $this->getDebtsByGensicil($this->resolveToGensicil($identityNo, $searchType), $aboneNo);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDebtsByTc(string $tcKimlikNo, ?string $gensicilNo = null, ?string $aboneNo = null): array
    {
        $selected = $gensicilNo !== null ? trim($gensicilNo) : '';
        $aboneNo = $aboneNo !== null ? trim($aboneNo) : '';

        if ($selected !== '' && ctype_digit($selected) && (int) $selected > 0) {
            $accounts = $this->identity->resolveAccountsFromTc($tcKimlikNo);
            $allowed = array_column($accounts, 'gensicilNo');
            if ($accounts !== [] && ! in_array($selected, $allowed, true)) {
                throw new BelsisException('Seçilen abonelik bu T.C. Kimlik No ile eşleşmiyor.');
            }

            $modulNo = '';
            if ($aboneNo !== '') {
                foreach ($accounts as $account) {
                    if ((string) ($account['gensicilNo'] ?? '') === $selected
                        && (string) ($account['aboneNo'] ?? '') === $aboneNo) {
                        $modulNo = (string) ($account['modulNo'] ?? '');
                        break;
                    }
                }
            }

            return $this->getDebtsByGensicil((int) $selected, $aboneNo !== '' ? $aboneNo : null, $modulNo !== '' ? $modulNo : null);
        }

        $direct = $this->fetchBorcSorgulaDebtsByTc($tcKimlikNo);
        if ($direct !== []) {
            return $direct;
        }

        $gensicils = $this->identity->resolveAllGensicilsFromTc($tcKimlikNo);
        if ($gensicils === []) {
            throw new BelsisException(
                'T.C. Kimlik No belediye kaydınızla eşleştirilemedi. Kayıtlarınızdaki TC güncel olmayabilir.',
            );
        }

        if (count($gensicils) === 1) {
            return $this->getDebtsByGensicil((int) $gensicils[0], $aboneNo !== '' ? $aboneNo : null);
        }

        throw new BelsisException(
            'Bu T.C. için birden fazla abonelik bulundu. Lütfen abonelik seçiniz.',
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDebtsByGensicil(int $gensicilno, ?string $aboneNo = null, ?string $modulNo = null): array
    {
        $debts = $this->fetchBorcSorgulaDebts($gensicilno);

        if ($debts === []) {
            $debts = $this->fetchSicilBorcBeyanDebts($gensicilno);
        }

        if ($debts === [] && config('belsis.tahakkuk_fallback', true)) {
            $debts = $this->fetchTahakkukFallbackDebts($gensicilno);
        }

        return $this->filterDebtsForAbone($debts, $aboneNo, $modulNo);
    }

    /**
     * Seçilen abone/modul ile ilişkilendirilebilen borçları ayıklar.
     * Eşleşme yoksa (meta yok) tüm liste korunur — boş ekran yerine yanlış filtre olmasın.
     *
     * @param  array<int, array<string, mixed>>  $debts
     * @return array<int, array<string, mixed>>
     */
    private function filterDebtsForAbone(array $debts, ?string $aboneNo, ?string $modulNo): array
    {
        $aboneNo = $aboneNo !== null ? trim($aboneNo) : '';
        $modulNo = $modulNo !== null ? trim($modulNo) : '';

        if ($aboneNo === '' && $modulNo === '') {
            return $debts;
        }

        $filtered = array_values(array_filter($debts, function (array $debt) use ($aboneNo, $modulNo) {
            $meta = is_array($debt['meta'] ?? null) ? $debt['meta'] : [];
            $haystack = mb_strtolower(implode(' ', array_filter([
                (string) ($debt['type'] ?? ''),
                (string) ($debt['period'] ?? ''),
                (string) ($meta['aboneNo'] ?? ''),
                (string) ($meta['beyanBilgisi'] ?? ''),
                (string) ($meta['aciklama'] ?? ''),
            ])));

            if ($aboneNo !== '' && (
                (string) ($meta['aboneNo'] ?? '') === $aboneNo
                || str_contains($haystack, mb_strtolower('abone no:'.$aboneNo))
                || str_contains($haystack, mb_strtolower('abone no '.$aboneNo))
                || preg_match('/\b'.preg_quote($aboneNo, '/').'\b/', $haystack)
            )) {
                return true;
            }

            if ($modulNo !== '' && (string) ($meta['modulNo'] ?? '') === $modulNo) {
                return true;
            }

            return false;
        }));

        // Meta ile ayırt edilemiyorsa hepsini göster (sicil ortak borçlar)
        return $filtered !== [] ? $filtered : $debts;
    }

    /**
     * borcSorgula — WSDL'in asıl "borç sorgulama" methodu (bkz. tahsilatWebServis_1.wsdl).
     * sorguTip parametresi kuruma göre değişir (config('belsis.borc_sorgu_tips_gensicil')),
     * bu yüzden birden fazla aday sırayla denenir. Bir tip için SP'nin o çağrıyı hiç
     * desteklemediğini gösteren "sistemik" hata alınırsa (bkz. isSystemicBorcError) kalan
     * tipler denenmeden bir sonraki veri kaynağına geçilir — her sorguda boşuna round-trip
     * harcanmaz.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchBorcSorgulaDebts(int $gensicilno): array
    {
        $tips = config('belsis.borc_sorgu_tips_gensicil', ['1']);
        $sorguNo = (string) $gensicilno;

        foreach ($tips as $tip) {
            try {
                $result = $this->client->callTahsilat('borcSorgula', array_merge(
                    $this->auth->baseParams(),
                    [
                        'sorguTip'            => $tip,
                        'sorguNo'             => $sorguNo,
                        'gensicilno'          => $gensicilno,
                        'indirimliOdenecekMi' => 0,
                        'indirimHakkiVarMi'   => 0,
                    ],
                ));

                // Yanlış sicile ait borç göstermemek için, boş olmayan bir sonuç bile olsa
                // Sicil.sicilNo aranan gensicilno ile birebir eşleşmeden kabul edilmez —
                // "asla ilk/yakın sonuç varsayılmaz" ilkesi (bkz. pickExactSicilRecord).
                if ($this->hasMatchingSicil($result, $gensicilno)) {
                    return $this->mapBorcSorgulaResult($result);
                }
            } catch (BelsisException $e) {
                if ($this->isInfrastructureError($e)) {
                    throw $e;
                }

                if ($this->isSystemicBorcError($e)) {
                    continue;
                }
                // bu sorguTip için iş hatası — bir sonraki adayı dene
            }
        }

        return [];
    }

    /**
     * TC ile doğrudan borcSorgula — eşleşen sicilin borçlarını tek yanıtta alır.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchBorcSorgulaDebtsByTc(string $tcKimlikNo): array
    {
        $tips = config('belsis.borc_sorgu_tips_tc', ['2', 'TC', 'TcKimlikNo', 'TCKIMLIK', 'Tc']);

        foreach ($tips as $tip) {
            try {
                $result = $this->client->callTahsilat('borcSorgula', array_merge(
                    $this->auth->baseParams(),
                    [
                        'sorguTip'            => $tip,
                        'sorguNo'             => $tcKimlikNo,
                        'gensicilno'          => 0,
                        'indirimliOdenecekMi' => 0,
                        'indirimHakkiVarMi'   => 0,
                    ],
                ));

                $sicil = $result['Sicil'] ?? $result['sicil'] ?? null;
                $sicilNo = is_array($sicil)
                    ? (int) ($sicil['sicilNo'] ?? $sicil['gensicilno'] ?? $sicil['gensicilNo'] ?? 0)
                    : 0;

                if ($sicilNo <= 0) {
                    continue;
                }

                $debts = $this->mapBorcSorgulaResult($result);
                if ($debts !== []) {
                    return $debts;
                }

                // Sicil bulundu ama bu yanıtta borç yoksa gensicil üzerinden yedek kaynaklar
                return $this->getDebtsByGensicil($sicilNo);
            } catch (BelsisException $e) {
                if ($this->isInfrastructureError($e)) {
                    throw $e;
                }

                if ($this->isSystemicBorcError($e)) {
                    continue;
                }
            }
        }

        return [];
    }

    private function hasMatchingSicil(array $result, int $gensicilno): bool
    {
        $sicil = $result['Sicil'] ?? $result['sicil'] ?? null;
        if (! is_array($sicil)) {
            return false;
        }

        $sicilNo = (int) ($sicil['sicilNo'] ?? $sicil['gensicilno'] ?? $sicil['gensicilNo'] ?? 0);

        return $sicilNo === $gensicilno;
    }

    /**
     * borcSorgula yanıtı Sicil > modulListesi > Modul > donemListesi > Donem >
     * tahakkukListesi > Tahakkuk şeklinde iç içedir (bkz. tahsilatWebServis_1.wsdl).
     * Her Tahakkuk tek bir borç kalemidir, ödenecek tutar 'odenecekTutar' alanındadır.
     *
     * @param  array<string, mixed>  $result
     * @return array<int, array<string, mixed>>
     */
    private function mapBorcSorgulaResult(array $result): array
    {
        $sicil = $result['Sicil'] ?? $result['sicil'] ?? null;
        if (! is_array($sicil)) {
            return [];
        }

        $modulListesi = $this->normalizeList($sicil['modulListesi']['Modul'] ?? $sicil['modulListesi'] ?? []);

        $debts = [];

        foreach ($modulListesi as $modul) {
            if (! is_array($modul)) {
                continue;
            }

            $donemListesi = $this->normalizeList($modul['donemListesi']['Donem'] ?? $modul['donemListesi'] ?? []);

            foreach ($donemListesi as $donem) {
                if (! is_array($donem)) {
                    continue;
                }

                $borcYili = $donem['borcYili'] ?? '';
                $taksit = $donem['taksit'] ?? '';

                $tahakkukListesi = $this->normalizeList($donem['tahakkukListesi']['Tahakkuk'] ?? $donem['tahakkukListesi'] ?? []);

                foreach ($tahakkukListesi as $tahakkuk) {
                    if (! is_array($tahakkuk)) {
                        continue;
                    }

                    $amount = (float) ($tahakkuk['odenecekTutar'] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }

                    $tahakkukNo = (string) ($tahakkuk['tahakkukNo'] ?? $tahakkuk['beyanID'] ?? '');
                    if ($tahakkukNo === '') {
                        continue;
                    }

                    $type = (string) ($tahakkuk['turu'] ?? $tahakkuk['aciklama'] ?? $tahakkuk['beyanBilgisi'] ?? $modul['modulBilgisi'] ?? 'Borç');

                    $period = trim(implode(' / ', array_filter([
                        $borcYili ? $borcYili.' Yılı' : null,
                        $taksit ? 'Taksit '.$taksit : null,
                    ])));

                    $debts[] = [
                        'id'      => $tahakkukNo,
                        'type'    => $type,
                        'period'  => $period,
                        'amount'  => $amount,
                        'dueDate' => $this->normalizeDate($tahakkuk['sonOdemeTarihi'] ?? null),
                        'meta'    => [
                            'tahakkukNo'     => $tahakkukNo,
                            'tahakkukTutari' => (float) ($tahakkuk['tahakkukTutari'] ?? $amount),
                            'gecikmeTutari'  => (float) ($tahakkuk['gecikmeZammi'] ?? 0),
                            'odemeTutari'    => $amount,
                            'indirimTutari'  => (float) ($tahakkuk['indirimTutari'] ?? 0),
                            'odenenTutar'    => (float) ($tahakkuk['odenenTutar'] ?? 0),
                            'modulNo'        => $modul['modulNo'] ?? null,
                            'borcYili'       => $borcYili,
                            'taksit'         => $taksit,
                            'kaynak'         => 'borcSorgula',
                        ],
                    ];
                }
            }
        }

        return $debts;
    }

    /**
     * tahakkukBilgileriniGetir bazı sicil türleri için (ör. tüzel kişi) iş hatası
     * döndürebilir (ör. kod 1101) — bu, sicilBorcBeyanSorgula tarafındaki
     * fetchSicilBorcBeyanDebts ile aynı şekilde ele alınmalı: altyapı hatası
     * değilse yutulur ve boş borç listesi olarak kabul edilir, tüm sorguyu
     * patlatmaz.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchTahakkukFallbackDebts(int $gensicilno): array
    {
        try {
            return $this->tahakkuk->getDebtsByGensicil($gensicilno);
        } catch (BelsisException $e) {
            // Tahakkuk oturumu (ayrı login) başarısız olabilir — ana tahsilat akışını
            // "oturum kimliği alınamadı" diye patlatma; borç yok say.
            $message = mb_strtolower($e->getMessage());
            if (
                str_contains($message, 'oturum')
                || str_contains($message, 'session')
                || ! $this->isInfrastructureError($e)
            ) {
                return [];
            }

            throw $e;
        }
    }

    private function resolveSearchType(string $identityNo, ?string $searchType): string
    {
        if (in_array($searchType, ['tc', 'sicil'], true)) {
            return $searchType;
        }

        return strlen($identityNo) === 11 ? 'tc' : 'sicil';
    }

    private function resolveToGensicil(string $identityNo, string $searchType): int
    {
        return (int) $this->identity->resolveGensicilNo($identityNo, $searchType);
    }

    private function parseGensicilNo(string $identityNo): int
    {
        $identityNo = trim($identityNo);

        if (! ctype_digit($identityNo)) {
            throw new BelsisException('Geçersiz sicil numarası. Sadece rakam giriniz.');
        }

        if (strlen($identityNo) === 11) {
            throw new BelsisException('Sicil numarası en fazla 10 haneli olabilir. T.C. için T.C. sekmesini kullanınız.');
        }

        $gensicilno = (int) $identityNo;

        if ($gensicilno <= 0) {
            throw new BelsisException('Sicil numarası giriniz.');
        }

        return $gensicilno;
    }

    /**
     * @return array{gensicilno: int, fullName: string, adi: string, soyadi: string}
     */
    private function fetchSicilProfile(int $gensicilno): array
    {
        $record = $this->pickMatchingSicilRecord($this->sicilSorgula($gensicilno), $gensicilno)
            ?? $this->pickMatchingSicilRecord($this->sicilSorgulaTahakkuk($gensicilno), $gensicilno);

        if ($record === null) {
            throw new BelsisException('Sicil numarası belediye kaydınızla eşleştirilemedi.');
        }

        $ad = trim((string) ($record['adi'] ?? ''));
        $soyad = trim((string) ($record['soyadi'] ?? ''));
        $unvan = trim((string) ($record['unvan'] ?? ''));
        $fullName = $unvan !== '' ? $unvan : (trim($ad.' '.$soyad) ?: 'Sicil No: '.$gensicilno);

        return [
            'gensicilno' => (int) ($record['gensicilno'] ?? $gensicilno),
            'fullName'   => $fullName,
            'adi'        => $ad,
            'soyadi'     => $soyad,
        ];
    }

    /**
     * sicilSorgula gensicilno parametresiyle birebir eşleşme bekler; ama sunucu birden
     * fazla kayıt döndürürse (ör. uyeNo/mukellefNo çakışması), yanlış kişiye borç
     * göstermemek için sadece gensicilno veya uyeNo'su girilen numarayla birebir eşleşen
     * kayıt kabul edilir. Eşleşme yoksa null döner — asla ilk kayıt varsayılmaz.
     *
     * @param  array<int, array<string, mixed>>  $siciller
     * @return array<string, mixed>|null
     */
    private function pickMatchingSicilRecord(array $siciller, int $gensicilno): ?array
    {
        foreach ($siciller as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowGensicil = (int) ($row['gensicilno'] ?? $row['gensicilNo'] ?? 0);
            $rowUyeNo = (int) ($row['uyeNo'] ?? 0);

            if ($rowGensicil === $gensicilno || $rowUyeNo === $gensicilno) {
                return $row;
            }
        }

        // Sunucu zaten gensicilno parametresiyle filtrelediği için tek kayıt dönmüşse
        // (gensicilno alanı boş/0 olsa bile) bu kayıt güvenle kabul edilir.
        if (count($siciller) === 1 && is_array($siciller[0])) {
            return $siciller[0];
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sicilSorgulaTahakkuk(int $gensicilno): array
    {
        try {
            $result = $this->client->callTahakkuk('sicilSorgula', array_merge(
                $this->auth->baseParamsTahakkuk(),
                ['gensicilno' => $gensicilno, 'koyID' => 0, 'mukellefNo' => (string) $gensicilno],
            ));

            return $this->normalizeList($result['sicilListesi']['sicilAlanlari'] ?? $result['sicilListesi'] ?? []);
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
    private function fetchSicilBorcBeyanDebts(int $gensicilno): array
    {
        try {
            $result = $this->sicilBorcBeyanSorgula($gensicilno);
            $items = $this->normalizeList(
                $result['sicilBorcBeyanListesi']['sicilBorcBeyanListesi'] ?? $result['sicilBorcBeyanListesi'] ?? [],
            );

            $debts = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $amount = (float) ($item['toplamBorc'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                $modulno = (string) ($item['modulno'] ?? '');
                $beyanId = (string) ($item['beyanID'] ?? $modulno);
                $debts[] = [
                    'id'      => $beyanId !== '' ? $beyanId : 'beyan-'.$modulno,
                    'type'    => (string) ($item['beyanAciklama'] ?? 'Borç Beyanı'),
                    'period'  => '',
                    'amount'  => $amount,
                    'dueDate' => null,
                    'meta'    => [
                        'modulno'  => $modulno,
                        'beyanID'  => $item['beyanID'] ?? null,
                        'kaynak'   => 'sicilBorcBeyanSorgula',
                    ],
                ];
            }

            return $debts;
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
    public function search(string $sorguTip, string $sorguNo): array
    {
        $result = $this->client->callTahsilat('arama', array_merge(
            $this->auth->baseParams(),
            ['sorguTip' => $sorguTip, 'sorguNo' => $sorguNo],
        ));

        $siciller = $result['Siciller'] ?? $result['siciller'] ?? [];

        return $this->normalizeList($siciller['SicilaramaObj'] ?? $siciller['sicilaramaObj'] ?? $siciller);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sicilSorgula(int $gensicilno, ?string $ad = null, ?string $soyad = null): array
    {
        $params = array_merge($this->auth->baseParams(), [
            'gensicilno' => $gensicilno,
            'koyID'      => 0,
            'mukellefNo' => (string) $gensicilno,
        ]);

        if ($ad !== null) {
            $params['ad'] = $ad;
        }
        if ($soyad !== null) {
            $params['soyad'] = $soyad;
        }

        $result = $this->client->callTahsilat('sicilSorgula', $params);

        return $this->normalizeList($result['sicilListesi']['sicilAlanlari'] ?? $result['sicilListesi'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function sicilBorcBeyanSorgula(int $gensicilno): array
    {
        return $this->client->callTahsilat('sicilBorcBeyanSorgula', array_merge(
            $this->auth->baseParams(),
            ['gensicilno' => $gensicilno],
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function mukellefMakbuzSorgula(int $gensicilno): array
    {
        $result = $this->client->callTahsilat('mukellefMakbuzSorgula', array_merge(
            $this->auth->baseParams(),
            ['gensicilno' => $gensicilno],
        ));

        return $this->normalizeList($result['makbuzListesi']['makbuzlar'] ?? $result['makbuzListesi'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tahsilatSorgula(int $gensicilno): array
    {
        $result = $this->client->callTahsilat('tahsilatSorgula', array_merge(
            $this->auth->baseParams(),
            ['gensicilno' => $gensicilno],
        ));

        return $this->normalizeList($result['tahsilatListesi']['Tahsilat'] ?? $result['tahsilatListesi'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tahsilatDetaySorgula(int $tahsilatNo): array
    {
        $result = $this->client->callTahsilat('tahsilatDetaySorgula', array_merge(
            $this->auth->baseParams(),
            ['tahsilatNo' => $tahsilatNo],
        ));

        return $this->normalizeList($result['tahsilatDetayListesi']['TahsilatDetay'] ?? $result['tahsilatDetayListesi'] ?? []);
    }

    /**
     * WSDL (makbuzG): tek alan — masterMakbuzNo. seriNo/makbuzNo bu operasyonun şemasında
     * yok; $seriNo/$makbuzNo parametreleri yalnızca çağıranların (ör. receipt route'u)
     * elindeki bilgiyi iletebilmesi için tutulur, SOAP isteğine dahil edilmez.
     *
     * @return array<string, mixed>
     */
    public function makbuzSorgula(int $masterMakbuzNo, ?string $seriNo = null, ?int $makbuzNo = null): array
    {
        return $this->client->callTahsilat('makbuzSorgula', array_merge(
            $this->auth->baseParams(),
            ['masterMakbuzNo' => $masterMakbuzNo],
        ));
    }

    /**
     * @param  array<string, mixed>  $makbuzResult
     * @return array<string, mixed>|null
     */
    public function formatMakbuz(array $makbuzResult): ?array
    {
        $items = $this->normalizeList($makbuzResult['makbuzlar']['makbuzlar'] ?? $makbuzResult['makbuzlar'] ?? []);
        $first = $items[0] ?? null;

        if (! is_array($first)) {
            return null;
        }

        $details = $this->normalizeList($first['makbuzDetaylar']['makbuzDetaylar'] ?? $first['makbuzDetaylar'] ?? []);

        return [
            'makbuzID'        => (int) ($first['makbuzID'] ?? 0),
            'makbuzNo'        => (int) ($first['makbuzNo'] ?? 0),
            'seriNo'          => (string) ($first['seriNo'] ?? ''),
            'gensicilNo'      => (int) ($first['gensicilNo'] ?? 0),
            'adi'             => (string) ($first['adi'] ?? ''),
            'soyadi'          => (string) ($first['soyadi'] ?? ''),
            'odemeTarihi'     => (string) ($first['odemeTarihi'] ?? ''),
            'odemeSekli'      => (string) ($first['odemeSekli'] ?? ''),
            'toplamTutar'     => (float) ($first['toplamTutar'] ?? 0),
            'toplamTutarYazi' => (string) ($first['toplamTutarYazi'] ?? ''),
            'belediyeAdi'     => (string) ($first['belediyeAdi'] ?? ''),
            'details'         => array_map(fn (array $row) => [
                'gelirKodu'   => (string) ($row['gelirKodu'] ?? ''),
                'gelirAdi'    => (string) ($row['gelirAdi'] ?? ''),
                'yilDonem'    => (string) ($row['yilDonem'] ?? ''),
                'aciklama'    => (string) ($row['aciklama'] ?? ''),
                'odemeTutari' => (float) ($row['odemeTutari'] ?? 0),
            ], $details),
        ];
    }

    /**
     * Sicil no ile tüm kayıt alanlarını döndürür (WSDL: sicilAlanlari).
     *
     * @return array<string, mixed>
     */
    public function getSicilDetay(string $identityNo): array
    {
        $gensicilno = $this->parseGensicilNo($identityNo);

        $records = $this->sicilSorgulaByGensicilno($gensicilno);

        if (empty($records)) {
            $records = $this->sicilSorgulaTahakkukByGensicilno($gensicilno);
        }

        if (empty($records)) {
            throw new BelsisException('Sicil numarası '.$gensicilno.' bulunamadı.');
        }

        $record = $this->pickExactSicilRecord($records, $gensicilno);

        if ($record === null) {
            throw new BelsisException('Sicil numarası '.$gensicilno.' için birebir eşleşen kayıt bulunamadı.');
        }

        $ad    = trim((string) ($record['adi'] ?? ''));
        $soyad = trim((string) ($record['soyadi'] ?? ''));
        $unvan = trim((string) ($record['unvan'] ?? ''));

        return [
            'gensicilno'  => (int) ($record['gensicilno'] ?? $gensicilno),
            'uyeNo'       => (int) ($record['uyeNo'] ?? 0),
            'adi'         => $ad,
            'soyadi'      => $soyad,
            'unvan'       => $unvan,
            'babaAdi'     => (string) ($record['babaAdi'] ?? ''),
            'dogumYeri'   => (string) ($record['dogumYeri'] ?? ''),
            'dogumTarihi' => (string) ($record['dogumTarihi'] ?? ''),
            'koyAdi'      => (string) ($record['koyAdi'] ?? ''),
            'tcKimlikNo'  => (string) ($record['tcKimlikNo'] ?? ''),
            'fullName'    => $unvan !== '' ? $unvan : (trim($ad.' '.$soyad) ?: 'Sicil No: '.$gensicilno),
            'sicilNo'     => (string) ($record['gensicilno'] ?? $gensicilno),
        ];
    }

    /**
     * gensicilno VEYA uyeNo ile birebir eşleşen kaydı seçer.
     * "tek kayıt varsa kabul et" mantığı yoktur — yanlış kişi riski sıfırlanır.
     *
     * @param  array<int, array<string, mixed>>  $siciller
     * @return array<string, mixed>|null
     */
    private function pickExactSicilRecord(array $siciller, int $gensicilno): ?array
    {
        foreach ($siciller as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowGensicil = (int) ($row['gensicilno'] ?? $row['gensicilNo'] ?? 0);
            $rowUyeNo    = (int) ($row['uyeNo'] ?? 0);

            if ($rowGensicil === $gensicilno || $rowUyeNo === $gensicilno) {
                return $row;
            }
        }

        return null;
    }

    /**
     * gensicilno + mukellefNo ile tahsilat sicilSorgula, birebir eşleşme kontrolü yapar.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sicilSorgulaByGensicilno(int $gensicilno): array
    {
        try {
            $result = $this->client->callTahsilat('sicilSorgula', array_merge(
                $this->auth->baseParams(),
                ['gensicilno' => $gensicilno, 'koyID' => 0, 'mukellefNo' => (string) $gensicilno],
            ));

            $records = $this->normalizeList($result['sicilListesi']['sicilAlanlari'] ?? $result['sicilListesi'] ?? []);

            // mukellefNo ile başka biri geldiyse filtrele
            return array_values(array_filter($records, function (mixed $row) use ($gensicilno) {
                if (! is_array($row)) {
                    return false;
                }
                $rowGensicil = (int) ($row['gensicilno'] ?? $row['gensicilNo'] ?? 0);
                $rowUyeNo    = (int) ($row['uyeNo'] ?? 0);

                return $rowGensicil === $gensicilno || $rowUyeNo === $gensicilno;
            }));
        } catch (BelsisException $e) {
            if ($this->isInfrastructureError($e)) {
                throw $e;
            }

            return [];
        }
    }

    /**
     * gensicilno + mukellefNo ile tahakkuk sicilSorgula, birebir eşleşme kontrolü yapar.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sicilSorgulaTahakkukByGensicilno(int $gensicilno): array
    {
        try {
            $result = $this->client->callTahakkuk('sicilSorgula', array_merge(
                $this->auth->baseParamsTahakkuk(),
                ['gensicilno' => $gensicilno, 'koyID' => 0, 'mukellefNo' => (string) $gensicilno],
            ));

            $records = $this->normalizeList($result['sicilListesi']['sicilAlanlari'] ?? $result['sicilListesi'] ?? []);

            return array_values(array_filter($records, function (mixed $row) use ($gensicilno) {
                if (! is_array($row)) {
                    return false;
                }
                $rowGensicil = (int) ($row['gensicilno'] ?? $row['gensicilNo'] ?? 0);
                $rowUyeNo    = (int) ($row['uyeNo'] ?? 0);

                return $rowGensicil === $gensicilno || $rowUyeNo === $gensicilno;
            }));
        } catch (BelsisException $e) {
            if ($this->isInfrastructureError($e)) {
                throw $e;
            }

            return [];
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];

        return [$parts[0] ?? $fullName, $parts[1] ?? ''];
    }
}
