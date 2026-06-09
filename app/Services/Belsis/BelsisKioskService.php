<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use Illuminate\Support\Facades\Log;

class BelsisKioskService
{
    public function __construct(
        private readonly BelsisTahakkukService $tahakkuk,
        private readonly BelsisTahsilatService $tahsilat,
        private readonly BelsisAuthService $auth,
    ) {}

    /**
     * @return array{identityNo: string, fullName: string, sicilNo: string}
     */
    public function getCitizen(string $identityNo): array
    {
        if (config('belsis.mock')) {
            return $this->mockCitizen($identityNo);
        }

        try {
            return $this->tahakkuk->getCitizen($identityNo);
        } catch (BelsisException $e) {
            if ($this->shouldRetryWithFreshSession($e)) {
                $this->auth->forgetSession();

                return $this->tahakkuk->getCitizen($identityNo);
            }
            throw $e;
        }
    }

    /**
     * @return array{debts: array<int, array<string, mixed>>}
     */
    public function getDebts(string $identityNo): array
    {
        if (config('belsis.mock')) {
            return ['debts' => $this->mockDebts()];
        }

        try {
            $debts = $this->tahakkuk->getDebts($identityNo);

            return ['debts' => $debts];
        } catch (BelsisException $e) {
            if ($this->shouldRetryWithFreshSession($e)) {
                $this->auth->forgetSession();

                return ['debts' => $this->tahakkuk->getDebts($identityNo)];
            }

            Log::error('Belsis borç sorgusu hatası', ['message' => $e->getMessage(), 'code' => $e->sonucKodu]);
            throw $e;
        }
    }

    /**
     * @param  array<int, string>  $debtIds
     */
    public function initiatePayment(string $identityNo, array $debtIds): array
    {
        $citizen = $this->getCitizen($identityNo);
        $debts = $this->getDebts($identityNo)['debts'];
        $selected = collect($debts)->whereIn('id', $debtIds)->values();

        if ($selected->isEmpty()) {
            throw new BelsisException('Seçilen borç bulunamadı.');
        }

        $total = $selected->sum('amount');
        $gensicilno = $citizen['sicilNo'];

        if (config('belsis.mock')) {
            return $this->tahsilat->initiateQrPayment($gensicilno, $debtIds, $total);
        }

        return $this->tahsilat->initiateQrPayment($gensicilno, $debtIds, $total);
    }

    /**
     * @param  array<int, string>  $debtIds
     */
    public function confirmPayment(string $identityNo, array $debtIds, string $transactionId): array
    {
        $citizen = $this->getCitizen($identityNo);
        $debts = $this->getDebts($identityNo)['debts'];

        if (config('belsis.mock')) {
            return [
                'transactionId' => $transactionId,
                'status'        => 'completed',
                'receiptNo'     => 'MKZ-'.random_int(100000, 999999),
            ];
        }

        $selectedDebts = collect($debts)->whereIn('id', $debtIds)->map(fn ($d) => [
            'id'     => (string) $d['id'],
            'amount' => (float) $d['amount'],
        ])->values()->all();

        try {
            return $this->tahsilat->confirmQrPayment(
                $citizen['sicilNo'],
                $selectedDebts,
                $transactionId,
            );
        } catch (BelsisException $e) {
            if ($this->shouldRetryWithFreshSession($e)) {
                $this->auth->forgetSession();

                return $this->tahsilat->confirmQrPayment(
                    $citizen['sicilNo'],
                    $selectedDebts,
                    $transactionId,
                );
            }
            throw $e;
        }
    }

    private function shouldRetryWithFreshSession(BelsisException $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return str_contains($message, 'oturum')
            || str_contains($message, 'session')
            || in_array($e->sonucKodu, ['1002', '1003', '401', '403'], true);
    }

    /**
     * @return array{identityNo: string, fullName: string, sicilNo: string}
     */
    private function mockCitizen(string $identityNo): array
    {
        return [
            'identityNo' => $identityNo,
            'fullName'   => 'Ahmet YILMAZ (Demo)',
            'sicilNo'    => strlen($identityNo) <= 10 ? $identityNo : '89874',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mockDebts(): array
    {
        return [
            ['id' => '33430362', 'type' => 'GAYRİSIHHİ İŞYERLERİ İÇİN TETKİK VE KONTROL ÜCRETİ', 'period' => '2024 Yılı / Taksit 1', 'amount' => 2250.00, 'dueDate' => '2024-05-27'],
            ['id' => '38024491', 'type' => 'Emlak Vergisi', 'period' => '2024 / 1. Taksit', 'amount' => 2450.00, 'dueDate' => '2024-03-31'],
            ['id' => '38024492', 'type' => 'Çevre Temizlik Vergisi', 'period' => '2024 Yılı', 'amount' => 380.50, 'dueDate' => '2024-12-31'],
        ];
    }
}
