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
        private readonly BelsisIdentityResolver $identity,
    ) {}

    /**
     * @return array{identityNo: string, fullName: string, sicilNo: string, adi?: string, soyadi?: string}
     */
    public function getCitizen(string $identityNo): array
    {
        $gensicilno = $this->identity->resolveGensicilNo($identityNo);
        $borc = $this->fetchBorcSorgula((int) $gensicilno, $identityNo);

        $sicil = $borc['Sicil'] ?? [];
        $fullName = trim((string) ($sicil['adiSoyadiUnvani'] ?? ''));

        if ($fullName === '') {
            $fullName = $this->lookupNameFromSicilSorgula((int) $gensicilno) ?? 'Sicil No: '.$gensicilno;
        }

        [$adi, $soyadi] = $this->splitName($fullName);

        return [
            'identityNo' => $identityNo,
            'fullName'   => $fullName,
            'sicilNo'    => (string) ($sicil['sicilNo'] ?? $gensicilno),
            'adi'        => $adi,
            'soyadi'     => $soyadi,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDebts(string $identityNo): array
    {
        $gensicilno = $this->identity->resolveGensicilNo($identityNo);
        $borc = $this->fetchBorcSorgula((int) $gensicilno, $identityNo);

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

    /**
     * @return array<string, mixed>
     */
    private function fetchBorcSorgula(int $gensicilno, string $identityNo): array
    {
        $params = $this->buildBorcSorgulaParams($gensicilno, $identityNo);

        try {
            return $this->client->callTahsilat('borcSorgula', $params);
        } catch (BelsisException $e) {
            if ($this->shouldRetryBorcSorgulaWithGensicilnoZero($params, $e)) {
                $params['gensicilno'] = 0;

                return $this->client->callTahsilat('borcSorgula', $params);
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBorcSorgulaParams(int $gensicilno, string $identityNo): array
    {
        $identityNo = trim($identityNo);

        $params = array_merge($this->auth->baseParams(), [
            'indirimliOdenecekMi' => 0,
            'indirimHakkiVarMi'   => 0,
        ]);

        if (strlen($identityNo) === 11 && ctype_digit($identityNo)) {
            return array_merge($params, [
                'sorguTip'   => (string) config('belsis.borc_sorgu_tip_tc', 'TC'),
                'sorguNo'    => $identityNo,
                'gensicilno' => $gensicilno,
            ]);
        }

        return array_merge($params, [
            'sorguTip'   => (string) config('belsis.borc_sorgu_tip_sicil', 'SICIL'),
            'sorguNo'    => (string) $gensicilno,
            'gensicilno' => $gensicilno,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function shouldRetryBorcSorgulaWithGensicilnoZero(array $params, BelsisException $e): bool
    {
        if (($params['gensicilno'] ?? 0) === 0) {
            return false;
        }

        $sorguTip = mb_strtoupper((string) ($params['sorguTip'] ?? ''));
        $tcTip = mb_strtoupper((string) config('belsis.borc_sorgu_tip_tc', 'TC'));

        if ($sorguTip !== $tcTip) {
            return false;
        }

        $message = mb_strtolower($e->getMessage());

        return str_contains($message, 'sicil')
            || str_contains($message, 'bulunamad')
            || str_contains($message, 'eşleş');
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
