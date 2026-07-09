<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;

class BelsisTahsilatService
{
    public function __construct(
        private readonly BelsisSoapClient $client,
        private readonly BelsisAuthService $auth,
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
     * @return array{transactionId: string, receiptNo: string, total: float, status: string}
     */
    public function confirmBankPayment(
        string $gensicilno,
        array $debts,
        string $transactionId,
        array $citizen = [],
    ): array {
        try {
            $result = $this->odemeYap($gensicilno, $debts, $citizen);

            return [
                'transactionId' => $transactionId,
                'receiptNo'     => (string) ($result['seriNo'] ?? '').'-'.($result['makbuzNo'] ?? ''),
                'total'         => (float) collect($debts)->sum('amount'),
                'status'        => 'completed',
                'makbuzID'      => $result['makbuzID'] ?? null,
            ];
        } catch (BelsisException $e) {
            throw new BelsisException('Ödeme kaydedilemedi: '.$e->getMessage(), $e->sonucKodu, $e->getCode(), $e);
        }
    }

    /**
     * @param  array<int, array{id: string, amount: float, meta?: array<string, mixed>}>  $debts
     * @param  array{adi?: string, soyadi?: string, fullName?: string}  $citizen
     * @return array<string, mixed>
     */
    private function odemeYap(string $gensicilno, array $debts, array $citizen): array
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
                'odemeSekli' => (int) config('belsis.odeme_sekli', 5),
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
