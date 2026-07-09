<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use App\Services\Belsis\Concerns\NormalizesBelsisLists;

/**
 * Tahakkuk web servisi — webservis/tahakkuk/tahakkukWebServis_1.wsdl methodları.
 */
class BelsisTahakkukService
{
    use NormalizesBelsisLists;

    public function __construct(
        private readonly BelsisSoapClient $client,
        private readonly BelsisAuthService $auth,
        private readonly BelsisIdentityResolver $identity,
    ) {}

    /**
     * @return array{identityNo: string, fullName: string, sicilNo: string}
     */
    public function getCitizen(string $identityNo): array
    {
        $gensicilno = (int) $this->identity->resolveGensicilNo($identityNo);
        $siciller = $this->sicilSorgula($gensicilno);

        $first = $siciller[0] ?? null;
        if (! is_array($first)) {
            return [
                'identityNo' => $identityNo,
                'fullName'   => 'Sicil No: '.$gensicilno,
                'sicilNo'    => (string) $gensicilno,
            ];
        }

        $ad = trim((string) ($first['adi'] ?? ''));
        $soyad = trim((string) ($first['soyadi'] ?? ''));
        $unvan = trim((string) ($first['unvan'] ?? ''));

        return [
            'identityNo' => $identityNo,
            'fullName'   => $unvan !== '' ? $unvan : trim($ad.' '.$soyad) ?: 'Sicil No: '.$gensicilno,
            'sicilNo'    => (string) ($first['gensicilno'] ?? $gensicilno),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDebts(string $identityNo): array
    {
        $gensicilno = $this->identity->resolveGensicilNo($identityNo);
        $result = $this->client->callTahakkuk('tahakkukBilgileriniGetir', array_merge(
            $this->auth->baseParams(),
            ['gensicilno' => (int) $gensicilno],
        ));

        $items = $this->extractTahakkukItems($result);

        return array_values(array_filter(
            array_map(fn (array $item) => $this->mapDebt($item), $items),
            fn (array $debt) => $debt['id'] !== '' && $debt['amount'] > 0,
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPaymentHistory(string $recId): array
    {
        $result = $this->client->callTahakkuk('tahakkukOdemeBilgileriniGetir', array_merge(
            $this->auth->baseParams(),
            ['recId' => $recId],
        ));

        $list = $result['tahakkukListesi']['TahakkukOdemeList'] ?? $result['tahakkukListesi'] ?? [];

        return $this->normalizeList($list);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTahakkukTurleri(): array
    {
        $result = $this->client->callTahakkuk('tahakkukTurleri', $this->auth->baseParams());

        return $this->normalizeList($result['tahakkukTurListesi']['tahakkukTurleri'] ?? $result['tahakkukTurListesi'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getKdvHesaplari(): array
    {
        $result = $this->client->callTahakkuk('kdvHesaplari', $this->auth->baseParams());

        return $this->normalizeList($result['kdvHesaplariListesi']['kdvHesaplari'] ?? $result['kdvHesaplariListesi'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getKdvOranlari(): array
    {
        $result = $this->client->callTahakkuk('kdvOranlari', $this->auth->baseParams());

        return $this->normalizeList($result['kdvOranlariListesi']['kdvOranlari'] ?? $result['kdvOranlariListesi'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function tahakkukEkle(array $payload): array
    {
        return $this->client->callTahakkuk('tahakkukEkle', array_merge(
            $this->auth->baseParams(),
            $payload,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function tahakkukIptal(string $tahId): array
    {
        return $this->client->callTahakkuk('tahakkukIptal', array_merge(
            $this->auth->baseParams(),
            ['tahId' => $tahId],
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function genmahSorgulaCombo(): array
    {
        $result = $this->client->callTahakkuk('genmahSorgulaCombo', $this->auth->baseParams());

        return $this->normalizeList($result['genmahListesi']['genmah'] ?? $result['genmahListesi'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function gmkSorgula(int $gensicilno): array
    {
        return $this->client->callTahakkuk('gmkSorgula', array_merge(
            $this->auth->baseParams(),
            ['gensicilno' => $gensicilno],
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sicilSorgula(int $gensicilno): array
    {
        try {
            $result = $this->client->callTahakkuk('sicilSorgula', array_merge(
                $this->auth->baseParams(),
                [
                    'gensicilno' => $gensicilno,
                    'koyID'      => 0,
                    'mukellefNo' => (string) $gensicilno,
                ],
            ));

            return $this->normalizeList($result['sicilListesi']['sicilAlanlari'] ?? $result['sicilListesi'] ?? []);
        } catch (BelsisException) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<int, array<string, mixed>>
     */
    private function extractTahakkukItems(array $result): array
    {
        $liste = $result['tahakkukListesi'] ?? [];

        if (isset($liste['TahakkukList'])) {
            return $this->normalizeList($liste['TahakkukList']);
        }

        return $this->normalizeList($liste);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapDebt(array $item): array
    {
        $tahId = (string) ($item['tahID'] ?? $item['tahId'] ?? $item['tahakkukId'] ?? $item['recId'] ?? '');
        $amount = (float) ($item['tutar'] ?? $item['borcTutari'] ?? 0);
        $dueDate = $this->normalizeDate($item['sonOdemeTarihi'] ?? $item['tahakkukTarihi'] ?? null);

        $borcYili = $item['borcYili'] ?? $item['borc_yili'] ?? '';
        $borcAyi  = $item['borcAyi'] ?? $item['borc_ayi'] ?? '';
        $taksit   = $item['taksit'] ?? '';

        $period = trim(implode(' / ', array_filter([
            $borcYili ? $borcYili.' Yılı' : null,
            $borcAyi ? 'Ay '.$borcAyi : null,
            $taksit ? 'Taksit '.$taksit : null,
        ]))) ?: ($item['aciklama'] ?? '');

        return [
            'id'      => $tahId,
            'type'    => $item['tahakkukAdi'] ?? $item['aciklama'] ?? 'Tahakkuk',
            'period'  => $period,
            'amount'  => $amount,
            'dueDate' => $dueDate,
            'meta'    => [
                'tahakkukNo'     => $tahId,
                'tahakkukTutari' => $amount,
                'gecikmeTutari'  => 0,
                'odemeTutari'    => $amount,
                'tahakkukTuru'   => $item['tahakkukTuru'] ?? $item['tturu'] ?? null,
                'aciklama'       => $item['aciklama'] ?? null,
                'borcYili'       => $borcYili,
                'borcAyi'        => $borcAyi,
                'taksit'         => $taksit,
            ],
        ];
    }
}
