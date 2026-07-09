<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use App\Services\Belsis\Concerns\NormalizesBelsisLists;

class BelsisTahsilatQueryService
{
    use NormalizesBelsisLists;

    public function __construct(
        private readonly BelsisSoapClient $client,
        private readonly BelsisAuthService $auth,
        private readonly BelsisBorcSorgulaService $borc,
        private readonly BelsisTahakkukService $tahakkuk,
    ) {}

    /**
     * @return array{identityNo: string, fullName: string, sicilNo: string, adi?: string, soyadi?: string}
     */
    public function getCitizen(string $identityNo, ?string $searchType = null): array
    {
        $identityNo = trim($identityNo);
        $searchType = $searchType ?? (strlen($identityNo) === 11 ? 'tc' : 'sicil');

        if ($searchType === 'sicil') {
            return $this->getCitizenByAccount($identityNo);
        }

        $gensicilno = null;
        $fullName = '';
        $adi = '';
        $soyadi = '';

        if (strlen($identityNo) === 11 && ctype_digit($identityNo)) {
            $gensicilno = $this->borc->resolveGensicilFromTc($identityNo)
                ?? $this->borc->resolveGensicilFromTcBorcResponse($identityNo);
        }

        if ($gensicilno !== null) {
            $profile = $this->fetchSicilProfile((int) $gensicilno);
            $gensicilno = (string) $profile['gensicilno'];
            $fullName = $profile['fullName'];
            $adi = $profile['adi'];
            $soyadi = $profile['soyadi'];
        } else {
            $borc = $this->borc->query($identityNo, $searchType);
            $gensicilno = $this->borc->extractSicilNo($borc, $identityNo);
            $sicil = $borc['Sicil'] ?? [];
            $fullName = trim((string) ($sicil['adiSoyadiUnvani'] ?? ''));

            if ($fullName === '') {
                $profile = $this->fetchSicilProfile((int) $gensicilno);
                $fullName = $profile['fullName'];
                $adi = $profile['adi'];
                $soyadi = $profile['soyadi'];
            }
        }

        if ($fullName === '' && $gensicilno !== null) {
            $fullName = 'Sicil No: '.$gensicilno;
        }

        if ($adi === '' && $soyadi === '') {
            [$adi, $soyadi] = $this->splitName($fullName);
        }

        return [
            'identityNo' => $identityNo,
            'fullName'   => $fullName,
            'sicilNo'    => $gensicilno ?? $identityNo,
            'searchType' => 'tc',
            'adi'        => $adi,
            'soyadi'     => $soyadi,
        ];
    }

    /**
     * @return array{identityNo: string, fullName: string, sicilNo: string, searchType: string, adi?: string, soyadi?: string}
     */
    private function getCitizenByAccount(string $accountNo): array
    {
        $borc = $this->borc->query($accountNo, 'sicil');
        $gensicilno = $this->borc->extractSicilNo($borc, $accountNo);
        $sicil = $borc['Sicil'] ?? [];
        $fullName = trim((string) ($sicil['adiSoyadiUnvani'] ?? ''));
        $adi = '';
        $soyadi = '';

        if ($fullName === '') {
            $profile = $this->fetchSicilProfile((int) $gensicilno);
            $fullName = $profile['fullName'];
            $adi = $profile['adi'];
            $soyadi = $profile['soyadi'];
        }

        if ($fullName === '') {
            $fullName = 'Sicil No: '.$gensicilno;
        }

        if ($adi === '' && $soyadi === '') {
            [$adi, $soyadi] = $this->splitName($fullName);
        }

        return [
            'identityNo' => $accountNo,
            'fullName'   => $fullName,
            'sicilNo'    => $gensicilno,
            'searchType' => 'sicil',
            'adi'        => $adi,
            'soyadi'     => $soyadi,
        ];
    }

    /**
     * @return array{gensicilno: int, fullName: string, adi: string, soyadi: string}
     */
    private function fetchSicilProfile(int $gensicilno): array
    {
        $siciller = $this->sicilSorgula($gensicilno);
        $first = $siciller[0] ?? null;

        if (! is_array($first)) {
            return [
                'gensicilno' => $gensicilno,
                'fullName'   => 'Sicil No: '.$gensicilno,
                'adi'        => '',
                'soyadi'     => '',
            ];
        }

        $ad = trim((string) ($first['adi'] ?? ''));
        $soyad = trim((string) ($first['soyadi'] ?? ''));
        $unvan = trim((string) ($first['unvan'] ?? ''));
        $fullName = $unvan !== '' ? $unvan : (trim($ad.' '.$soyad) ?: 'Sicil No: '.$gensicilno);

        return [
            'gensicilno' => (int) ($first['gensicilno'] ?? $gensicilno),
            'fullName'   => $fullName,
            'adi'        => $ad,
            'soyadi'     => $soyadi,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDebts(string $identityNo, ?string $searchType = null): array
    {
        $identityNo = trim($identityNo);
        $searchType = $searchType ?? (strlen($identityNo) === 11 ? 'tc' : 'sicil');
        $debts = [];
        $borc = null;

        try {
            $borc = $this->borc->query($identityNo, $searchType);
            $debts = $this->extractDebtsFromBorc($borc);
        } catch (BelsisException $e) {
            if ($e->sonucKodu !== '1004' && ! $this->isOnlyEmptyDebtError($e)) {
                throw $e;
            }
        }

        $gensicilno = null;
        if ($searchType === 'sicil') {
            $gensicilno = $this->borc->resolveGensicilFromAccount($identityNo, $borc);
        } elseif (strlen($identityNo) === 11) {
            $gensicilno = $this->borc->resolveGensicilFromTc($identityNo)
                ?? $this->borc->resolveGensicilFromTcBorcResponse($identityNo);
        }

        if ($debts === [] && $gensicilno !== null) {
            $debts = $this->fetchSicilBorcBeyanDebts((int) $gensicilno);
        }

        if ($debts === [] && config('belsis.tahakkuk_fallback', true)) {
            try {
                if ($gensicilno !== null) {
                    $debts = $this->tahakkuk->getDebtsByGensicil((int) $gensicilno);
                } else {
                    $debts = $this->tahakkuk->getDebts($identityNo, $searchType);
                }
            } catch (BelsisException) {
                // tahakkuk da boşsa boş liste
            }
        }

        return $debts;
    }

    private function isOnlyEmptyDebtError(BelsisException $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return in_array($e->sonucKodu, ['1004'], true)
            || str_contains($message, 'online tahsilatta görüntülenecek borç yok');
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
        } catch (BelsisException) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $borc
     * @return array<int, array<string, mixed>>
     */
    private function extractDebtsFromBorc(array $borc): array
    {
        $debts = [];
        $sicil = $borc['Sicil'] ?? [];
        $moduller = $this->normalizeList($sicil['modulListesi']['Modul'] ?? $sicil['modulListesi'] ?? []);

        foreach ($moduller as $modul) {
            if (! is_array($modul)) {
                continue;
            }

            $donemler = $this->normalizeList($modul['donemListesi']['Donem'] ?? $modul['donemListesi'] ?? []);

            foreach ($donemler as $donem) {
                if (! is_array($donem)) {
                    continue;
                }

                $tahakkuklar = $this->normalizeList($donem['tahakkukListesi']['Tahakkuk'] ?? $donem['tahakkukListesi'] ?? []);

                foreach ($tahakkuklar as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $debt = $this->mapDebt($item, $donem);
                    if ($debt['amount'] > 0) {
                        $debts[] = $debt;
                    }
                }
            }
        }

        return $debts;
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
     * @return array<string, mixed>
     */
    public function makbuzSorgula(int $masterMakbuzNo, ?string $seriNo = null, ?int $makbuzNo = null): array
    {
        $params = array_merge($this->auth->baseParams(), [
            'masterMakbuzNo' => $masterMakbuzNo,
        ]);

        if ($seriNo !== null) {
            $params['SeriNo'] = $seriNo;
        }
        if ($makbuzNo !== null) {
            $params['MakbuzNo'] = $makbuzNo;
        }

        return $this->client->callTahsilat('makbuzSorgula', $params);
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

    private function lookupNameFromSicilSorgula(int $gensicilno): ?string
    {
        try {
            $siciller = $this->sicilSorgula($gensicilno);
            $first = $siciller[0] ?? null;

            if (! is_array($first)) {
                return null;
            }

            $ad = trim((string) ($first['adi'] ?? ''));
            $soyad = trim((string) ($first['soyadi'] ?? ''));

            return trim($ad.' '.$soyad) ?: null;
        } catch (BelsisException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $donem
     * @return array<string, mixed>
     */
    private function mapDebt(array $item, array $donem): array
    {
        $tahakkukNo = (string) ($item['tahakkukNo'] ?? '');
        $odenecek = (float) ($item['odenecekTutar'] ?? 0);
        $tahakkukTutari = (float) ($item['tahakkukTutari'] ?? $odenecek);
        $gecikme = (float) ($item['gecikmeZammi'] ?? 0);

        $period = trim(implode(' / ', array_filter([
            isset($donem['borcYili']) ? $donem['borcYili'].' Yılı' : null,
            isset($donem['taksit']) ? 'Taksit '.$donem['taksit'] : null,
        ])));

        return [
            'id'      => $tahakkukNo,
            'type'    => (string) ($item['turu'] ?? $item['aciklama'] ?? $item['beyanBilgisi'] ?? 'Tahakkuk'),
            'period'  => $period,
            'amount'  => $odenecek,
            'dueDate' => $this->normalizeDate($item['sonOdemeTarihi'] ?? null),
            'meta'    => [
                'tahakkukNo'      => $tahakkukNo,
                'tahakkukTutari'  => $tahakkukTutari,
                'gecikmeTutari'   => $gecikme,
                'odemeTutari'     => $odenecek,
                'aciklama'        => $item['aciklama'] ?? null,
                'beyanBilgisi'    => $item['beyanBilgisi'] ?? null,
            ],
        ];
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
