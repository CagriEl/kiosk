<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;

class BelsisTahsilatService
{
    public function __construct(
        private readonly BelsisSoapClient $client,
        private readonly BelsisAuthService $auth,
        private readonly BelsisTahsilatQueryService $query,
        private readonly BelsisTahsilatCatalogService $catalog,
    ) {}

    /**
     * @param  array<int, string>  $tahIds
     * @return array{transactionId: string, total: float, status: string, paymentMethod: string}
     */
    public function initiateBankPayment(string $gensicilno, array $tahIds, float $total): array
    {
        return [
            'transactionId' => 'TXN-'.now()->timestamp,
            'total'         => $total,
            'status'        => 'pending',
            'paymentMethod' => 'bank',
        ];
    }

    /**
     * @param  array<int, array{id: string, amount: float, meta?: array<string, mixed>}>  $debts
     * @param  array{adi?: string, soyadi?: string, fullName?: string}  $citizen
     * @return array<string, mixed>
     */
    public function confirmBankPayment(
        string $gensicilno,
        array $debts,
        string $transactionId,
        array $citizen = [],
    ): array {
        try {
            $result = $this->odemeYapTahakkuklu($gensicilno, $debts, $citizen);
            $receipt = $this->fetchReceipt($result);

            return [
                'transactionId' => $transactionId,
                'receiptNo'     => trim(($result['seriNo'] ?? '').'-'.($result['makbuzNo'] ?? ''), '-'),
                'total'         => (float) collect($debts)->sum('amount'),
                'status'        => 'completed',
                'makbuzID'      => (int) ($result['makbuzID'] ?? 0),
                'makbuzNo'      => (int) ($result['makbuzNo'] ?? 0),
                'seriNo'        => (string) ($result['seriNo'] ?? ''),
                'receipt'       => $receipt,
            ];
        } catch (BelsisException $e) {
            throw new BelsisException('Ödeme kaydedilemedi: '.$e->getMessage(), $e->sonucKodu, $e->getCode(), $e);
        }
    }

    /**
     * Tahakkuksuz ödeme (webservis dokümantasyonu — odemeYap tahakkuksuzTahsilat).
     *
     * @param  array<int, array{tturu: int, gelirKodu?: string, gelirAdi?: string, aciklama?: string, odemeTutari: float, kdvTutar?: float, kdvOran?: int}>  $items
     * @param  array{adi?: string, soyadi?: string, fullName?: string}  $citizen
     * @return array<string, mixed>
     */
    public function odemeYapTahakkuksuz(string $gensicilno, array $items, array $citizen = []): array
    {
        [$adi, $soyadi] = $this->resolvePaymentName($citizen);

        $tahakkuksuzItems = [];
        foreach ($items as $item) {
            $tahakkuksuzItems[] = [
                'tturu'        => (int) ($item['tturu'] ?? 0),
                'gelirKodu'    => (string) ($item['gelirKodu'] ?? ''),
                'gelirAdi'     => (string) ($item['gelirAdi'] ?? ''),
                'aciklama'     => (string) ($item['aciklama'] ?? ''),
                'odemeTutari'  => (float) ($item['odemeTutari'] ?? 0),
                'kdvTutar'     => (float) ($item['kdvTutar'] ?? 0),
                'kdvOran'      => (int) ($item['kdvOran'] ?? 0),
            ];
        }

        return $this->client->callTahsilat('odemeYap', array_merge(
            $this->auth->baseParams(),
            [
                'gensicilno' => (int) $gensicilno,
                'odemeSekli' => $this->resolveOdemeSekli(),
                'adi'        => $adi,
                'soyadi'     => $soyadi,
                'tahakkukluTahsilat' => [
                    '__list'  => 'odemeTahakkukluTahsilat',
                    '__items' => [],
                ],
                'tahakkuksuzTahsilat' => [
                    '__list'  => 'odemeTahakkuksuzTahsilat',
                    '__items' => $tahakkuksuzItems,
                ],
            ],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function makbuzIptal(int $masterMakbuzNo, string $seriNo, int $makbuzNo): array
    {
        return $this->client->callTahsilat('makbuzIptal', array_merge(
            $this->auth->baseParams(),
            [
                'masterMakbuzNo' => $masterMakbuzNo,
                'SeriNo'         => $seriNo,
                'MakbuzNo'       => $makbuzNo,
            ],
        ));
    }

    /**
     * @param  array<int, array{id: string, amount: float, meta?: array<string, mixed>}>  $debts
     * @param  array{adi?: string, soyadi?: string, fullName?: string}  $citizen
     * @return array<string, mixed>
     */
    private function odemeYapTahakkuklu(string $gensicilno, array $debts, array $citizen): array
    {
        $items = [];
        foreach ($debts as $debt) {
            $meta = $debt['meta'] ?? [];
            $items[] = [
                'tahakkukNo'     => (int) ($meta['tahakkukNo'] ?? $debt['id']),
                'tahakkukTutari' => (float) ($meta['tahakkukTutari'] ?? $debt['amount']),
                'gecikmeTutari'  => (float) ($meta['gecikmeTutari'] ?? 0),
                'odemeTutari'    => (float) ($meta['odemeTutari'] ?? $debt['amount']),
            ];
        }

        [$adi, $soyadi] = $this->resolvePaymentName($citizen);

        return $this->client->callTahsilat('odemeYap', array_merge(
            $this->auth->baseParams(),
            [
                'gensicilno' => (int) $gensicilno,
                'odemeSekli' => $this->resolveOdemeSekli(),
                'adi'        => $adi,
                'soyadi'     => $soyadi,
                'tahakkukluTahsilat' => [
                    '__list'  => 'odemeTahakkukluTahsilat',
                    '__items' => $items,
                ],
            ],
        ));
    }

    /**
     * @param  array<string, mixed>  $odemeResult
     * @return array<string, mixed>|null
     */
    private function fetchReceipt(array $odemeResult): ?array
    {
        $makbuzId = (int) ($odemeResult['makbuzID'] ?? 0);
        if ($makbuzId === 0) {
            return null;
        }

        try {
            $result = $this->query->makbuzSorgula(
                $makbuzId,
                isset($odemeResult['seriNo']) ? (string) $odemeResult['seriNo'] : null,
                isset($odemeResult['makbuzNo']) ? (int) $odemeResult['makbuzNo'] : null,
            );

            return $this->query->formatMakbuz($result);
        } catch (BelsisException) {
            return [
                'makbuzID' => $makbuzId,
                'makbuzNo' => (int) ($odemeResult['makbuzNo'] ?? 0),
                'seriNo'   => (string) ($odemeResult['seriNo'] ?? ''),
            ];
        }
    }

    private function resolveOdemeSekli(): int
    {
        $configured = (int) config('belsis.odeme_sekli', 5);
        if ($configured > 0) {
            return $configured;
        }

        try {
            $methods = $this->catalog->getOdemeSekilleri();
            $krediKarti = collect($methods)->first(fn (array $m) => str_contains(mb_strtolower($m['name']), 'kredi'));
            if ($krediKarti) {
                return (int) $krediKarti['id'];
            }

            return (int) ($methods[0]['id'] ?? 5);
        } catch (BelsisException) {
            return 5;
        }
    }

    /**
     * @param  array{adi?: string, soyadi?: string, fullName?: string}  $citizen
     * @return array{0: string, 1: string}
     */
    private function resolvePaymentName(array $citizen): array
    {
        $adi = trim((string) ($citizen['adi'] ?? ''));
        $soyadi = trim((string) ($citizen['soyadi'] ?? ''));

        if ($adi !== '') {
            return [$adi, $soyadi];
        }

        $parts = preg_split('/\s+/', trim((string) ($citizen['fullName'] ?? '')), 2) ?: [];

        return [$parts[0] ?? 'VATANDAS', $parts[1] ?? ''];
    }
}
