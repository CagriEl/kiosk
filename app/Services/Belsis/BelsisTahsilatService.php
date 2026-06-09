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
     * @param  array<int, array{id: string, amount: float}>  $debts
     * @return array{transactionId: string, receiptNo: string, total: float, status: string}
     */
    public function collectPayment(string $gensicilno, array $debts): array
    {
        $receipts = [];
        $paidTotal = 0.0;

        foreach ($debts as $debt) {
            $tahId = (string) $debt['id'];
            $amount = (float) $debt['amount'];

            $result = $this->client->callTahsilat('tahsilatEkle', array_merge(
                $this->auth->baseParams(),
                [
                    'gensicilno'  => $gensicilno,
                    'tahID'       => $tahId,
                    'odemeTutari' => $amount,
                    'odemeTuru'   => 'BANKA',
                    'aciklama'    => 'Kiosk Banka Kartı Ödemesi',
                    'kulno'       => 0,
                ],
            ));

            $receipts[] = (string) ($result['makbuzNo'] ?? $result['tahsilatId'] ?? $tahId);
            $paidTotal += (float) ($result['odemeTutari'] ?? $amount);
        }

        $transactionId = 'TXN-'.now()->timestamp;

        return [
            'transactionId' => $transactionId,
            'receiptNo'     => implode(',', $receipts),
            'total'         => $paidTotal,
            'status'        => 'completed',
        ];
    }

    /**
     * Banka kartı ödemesi başlatma — POS cihazına yönlendirme için referans üretir.
     *
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
     * @param  array<int, array{id: string, amount: float}>  $debts
     * @return array{transactionId: string, receiptNo: string, total: float, status: string}
     */
    public function confirmBankPayment(string $gensicilno, array $debts, string $transactionId): array
    {
        try {
            $result = $this->collectPayment($gensicilno, $debts);
            $result['transactionId'] = $transactionId;

            return $result;
        } catch (BelsisException $e) {
            throw new BelsisException('Ödeme kaydedilemedi: '.$e->getMessage(), $e->sonucKodu, $e->getCode(), $e);
        }
    }
}
