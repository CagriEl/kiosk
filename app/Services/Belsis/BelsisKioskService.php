<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use Illuminate\Support\Facades\Log;

class BelsisKioskService
{
    public function __construct(
        private readonly BelsisTahsilatQueryService $query,
        private readonly BelsisTahsilatService $tahsilat,
        private readonly BelsisAuthService $auth,
    ) {}

    /**
     * @return array{identityNo: string, fullName: string, sicilNo: string}
     */
    public function getCitizen(string $identityNo): array
    {
        if ($this->shouldUseMock($identityNo)) {
            return $this->mockCitizen($identityNo);
        }

        try {
            return $this->query->getCitizen($identityNo);
        } catch (BelsisException $e) {
            if ($this->shouldRetryWithFreshSession($e)) {
                $this->auth->forgetSession();

                return $this->query->getCitizen($identityNo);
            }
            throw $e;
        }
    }

    /**
     * @return array{debts: array<int, array<string, mixed>>}
     */
    public function getDebts(string $identityNo): array
    {
        if ($this->shouldUseMock($identityNo)) {
            return ['debts' => $this->mockDebts()];
        }

        try {
            return ['debts' => $this->query->getDebts($identityNo)];
        } catch (BelsisException $e) {
            if ($this->shouldRetryWithFreshSession($e)) {
                $this->auth->forgetSession();

                return ['debts' => $this->query->getDebts($identityNo)];
            }

            Log::error('Belsis borç sorgusu hatası', ['message' => $e->getMessage(), 'code' => $e->sonucKodu]);
            throw $e;
        }
    }

    /**
     * @param  array<int, string>  $debtIds
     * @return array{transactionId: string, total: float, status: string, paymentMethod: string}
     */
    public function initiatePayment(string $identityNo, array $debtIds): array
    {
        $citizen = $this->getCitizen($identityNo);
        $debts = $this->getDebts($identityNo)['debts'];
        $selected = collect($debts)->whereIn('id', $debtIds)->values();

        if ($selected->isEmpty()) {
            throw new BelsisException('Seçilen borç bulunamadı.');
        }

        return $this->tahsilat->initiateBankPayment(
            $citizen['sicilNo'],
            $debtIds,
            (float) $selected->sum('amount'),
        );
    }

    /**
     * @param  array<int, string>  $debtIds
     * @return array{transactionId: string, status: string, receiptNo?: string}
     */
    public function confirmPayment(string $identityNo, array $debtIds, string $transactionId): array
    {
        $citizen = $this->getCitizen($identityNo);
        $debts = $this->getDebts($identityNo)['debts'];

        if ($this->shouldUseMock($identityNo)) {
            return [
                'transactionId' => $transactionId,
                'status'        => 'completed',
                'receiptNo'     => 'MKZ-'.random_int(100000, 999999),
            ];
        }

        $selectedDebts = collect($debts)->whereIn('id', $debtIds)->values()->all();

        try {
            return $this->tahsilat->confirmBankPayment(
                $citizen['sicilNo'],
                $selectedDebts,
                $transactionId,
                $citizen,
            );
        } catch (BelsisException $e) {
            if ($this->shouldRetryWithFreshSession($e)) {
                $this->auth->forgetSession();

                return $this->tahsilat->confirmBankPayment(
                    $citizen['sicilNo'],
                    $selectedDebts,
                    $transactionId,
                    $citizen,
                );
            }
            throw $e;
        }
    }

    private function shouldUseMock(string $identityNo): bool
    {
        if (! config('belsis.mock')) {
            return false;
        }

        return in_array(trim($identityNo), config('belsis.mock_sicils', []), true);
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
            'sicilNo'    => $identityNo,
            'adi'        => 'Ahmet',
            'soyadi'     => 'YILMAZ (Demo)',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mockDebts(): array
    {
        return [
            [
                'id' => '33430362', 'type' => 'GAYRİSIHHİ İŞYERLERİ İÇİN TETKİK VE KONTROL ÜCRETİ',
                'period' => '2024 Yılı / Taksit 1', 'amount' => 2250.00, 'dueDate' => '2024-05-27',
                'meta' => ['tahakkukNo' => '33430362', 'tahakkukTutari' => 2250, 'gecikmeTutari' => 0, 'odemeTutari' => 2250],
            ],
            [
                'id' => '38024491', 'type' => 'Emlak Vergisi',
                'period' => '2024 / 1. Taksit', 'amount' => 2450.00, 'dueDate' => '2024-03-31',
                'meta' => ['tahakkukNo' => '38024491', 'tahakkukTutari' => 2450, 'gecikmeTutari' => 0, 'odemeTutari' => 2450],
            ],
        ];
    }
}
