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
                    'odemeTuru'   => 'KIOSK',
                    'aciklama'    => 'Kiosk QR Ödeme',
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
     * QR ödeme başlatma — tahsilat öncesi referans üretir.
     *
     * @param  array<int, string>  $tahIds
     * @return array{transactionId: string, qrCodeUrl: string, total: float, status: string}
     */
    public function initiateQrPayment(string $gensicilno, array $tahIds, float $total): array
    {
        $transactionId = 'TXN-'.now()->timestamp;
        $qrPayload = implode('|', ['BELEDIYE', $gensicilno, number_format($total, 2, '.', ''), $transactionId, ...$tahIds]);
        $qrData = urlencode($qrPayload);

        return [
            'transactionId' => $transactionId,
            'qrCodeUrl'     => 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data='.$qrData.'&bgcolor=ffffff&color=1e5a9e',
            'total'         => $total,
            'status'        => 'pending',
        ];
    }

    /**
     * @param  array<int, string>  $tahIds
     */
    public function confirmQrPayment(string $gensicilno, array $debts, string $transactionId): array
    {
        try {
            return $this->collectPayment($gensicilno, $debts);
        } catch (BelsisException $e) {
            throw new BelsisException('Ödeme kaydedilemedi: '.$e->getMessage(), $e->sonucKodu, $e->getCode(), $e);
        }
    }
}
