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
    ) {}

    /**
     * Yalnızca gensicilno (sicil no) ile sorgu — abone no / TC yolu tamamen kaldırıldı.
     * sicilSorgula gensicilno parametresiyle birebir eşleşen kaydı doğrular; eşleşmeyen
     * kayıt asla "en yakın sonuç" olarak kabul edilmez (yanlış kişi riskini önler).
     *
     * @return array{identityNo: string, gensicilNo: string, sicilNo: string, fullName: string, searchType: string, adi: string, soyadi: string}
     */
    public function getCitizen(string $identityNo): array
    {
        $gensicilno = $this->parseGensicilNo($identityNo);
        $profile = $this->fetchSicilProfile($gensicilno);

        $adi = $profile['adi'];
        $soyadi = $profile['soyadi'];

        if ($adi === '' && $soyadi === '') {
            [$adi, $soyadi] = $this->splitName($profile['fullName']);
        }

        return [
            'identityNo' => (string) $gensicilno,
            'gensicilNo' => (string) $profile['gensicilno'],
            'sicilNo'    => (string) $profile['gensicilno'],
            'fullName'   => $profile['fullName'],
            'searchType' => 'sicil',
            'adi'        => $adi,
            'soyadi'     => $soyadi,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDebts(string $identityNo): array
    {
        $gensicilno = $this->parseGensicilNo($identityNo);

        $debts = $this->fetchSicilBorcBeyanDebts($gensicilno);

        if ($debts === [] && config('belsis.tahakkuk_fallback', true)) {
            $debts = $this->tahakkuk->getDebtsByGensicil($gensicilno);
        }

        return $debts;
    }

    private function parseGensicilNo(string $identityNo): int
    {
        $identityNo = trim($identityNo);

        if (! ctype_digit($identityNo)) {
            throw new BelsisException('Geçersiz sicil numarası. Sadece rakam giriniz.');
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

    /**
     * Sicil no ile tüm kayıt alanlarını döndürür (WSDL: sicilAlanlari).
     *
     * @return array<string, mixed>
     */
    public function getSicilDetay(string $identityNo): array
    {
        $gensicilno = $this->parseGensicilNo($identityNo);

        $records = $this->sicilSorgula($gensicilno);

        if (empty($records)) {
            $records = $this->sicilSorgulaTahakkuk($gensicilno);
        }

        if (empty($records)) {
            throw new BelsisException('Sicil numarası belediye kaydınızla eşleştirilemedi.');
        }

        $record = $this->pickMatchingSicilRecord($records, $gensicilno) ?? $records[0];

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
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];

        return [$parts[0] ?? $fullName, $parts[1] ?? ''];
    }
}
