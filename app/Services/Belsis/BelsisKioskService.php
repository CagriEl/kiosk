<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use Illuminate\Support\Facades\Log;

class BelsisKioskService
{
    public function __construct(
        private readonly BelsisTahsilatQueryService $query,
        private readonly BelsisTahsilatService $tahsilat,
        private readonly BelsisTahsilatCatalogService $catalog,
        private readonly BelsisAuthService $auth,
    ) {}

    /**
     * @return array{identityNo: string, fullName: string, sicilNo: string, searchType?: string}
     */
    public function getCitizen(string $identityNo, ?string $searchType = null): array
    {
        if ($this->shouldUseMock($identityNo)) {
            return $this->mockCitizen($identityNo, $searchType);
        }

        return $this->withSessionRetry(fn () => $this->query->getCitizen($identityNo, $searchType));
    }

    /**
     * @return array<string, mixed>
     */
    public function getSicilDetay(string $identityNo): array
    {
        return $this->withSessionRetry(fn () => $this->query->getSicilDetay($identityNo));
    }

    /**
     * @return array{debts: array<int, array<string, mixed>>}
     */
    public function getDebts(
        string $identityNo,
        ?string $searchType = null,
        ?string $gensicilNo = null,
        ?string $aboneNo = null,
    ): array {
        if ($this->shouldUseMock($identityNo)) {
            return ['debts' => $this->mockDebts()];
        }

        try {
            return ['debts' => $this->withSessionRetry(
                fn () => $this->query->getDebts($identityNo, $searchType, $gensicilNo, $aboneNo),
            )];
        } catch (BelsisException $e) {
            Log::error('Belsis borç sorgusu hatası', ['message' => $e->getMessage(), 'code' => $e->sonucKodu]);
            throw $e;
        }
    }

    /**
     * @return array{methods: array<int, array{id: int, name: string}>}
     */
    public function getPaymentMethods(): array
    {
        if (config('belsis.mock')) {
            return [
                'methods' => [
                    ['id' => 5, 'name' => 'Kredi Kartı (Demo)'],
                    ['id' => 2, 'name' => 'Banka (Demo)'],
                ],
            ];
        }

        return ['methods' => $this->withSessionRetry(fn () => $this->catalog->getOdemeSekilleri())];
    }

    /**
     * @return array<string, mixed>
     */
    public function getReceipt(int $makbuzId, ?string $seriNo = null, ?int $makbuzNo = null): array
    {
        $result = $this->withSessionRetry(fn () => $this->query->makbuzSorgula($makbuzId, $seriNo, $makbuzNo));
        $formatted = $this->query->formatMakbuz($result);

        if ($formatted === null) {
            throw new BelsisException('Makbuz bilgisi bulunamadı.');
        }

        return $formatted;
    }

    /**
     * @param  array<int, string>  $debtIds
     * @return array{transactionId: string, total: float, status: string, paymentMethod: string}
     */
    public function initiatePayment(
        string $identityNo,
        array $debtIds,
        ?string $searchType = null,
        ?string $gensicilNo = null,
        ?string $aboneNo = null,
    ): array {
        $citizen = $this->resolveCitizenForPayment($identityNo, $searchType, $gensicilNo, $aboneNo);
        $debts = $this->getDebts(
            $identityNo,
            $searchType,
            $citizen['gensicilNo'] ?? $gensicilNo,
            $citizen['aboneNo'] ?? $aboneNo,
        )['debts'];
        $selected = collect($debts)->whereIn('id', $debtIds)->values();

        if ($selected->isEmpty()) {
            throw new BelsisException('Seçilen borç bulunamadı.');
        }

        return $this->tahsilat->initiateBankPayment(
            $citizen['gensicilNo'] ?? $citizen['sicilNo'] ?? $identityNo,
            $debtIds,
            (float) $selected->sum('amount'),
        );
    }

    /**
     * @param  array<int, string>  $debtIds
     * @return array<string, mixed>
     */
    public function confirmPayment(
        string $identityNo,
        array $debtIds,
        string $transactionId,
        ?string $searchType = null,
        ?string $gensicilNo = null,
        ?string $aboneNo = null,
    ): array {
        $citizen = $this->resolveCitizenForPayment($identityNo, $searchType, $gensicilNo, $aboneNo);
        $debts = $this->getDebts(
            $identityNo,
            $searchType,
            $citizen['gensicilNo'] ?? $gensicilNo,
            $citizen['aboneNo'] ?? $aboneNo,
        )['debts'];

        if ($this->shouldUseMock($identityNo)) {
            return [
                'transactionId' => $transactionId,
                'status'        => 'completed',
                'receiptNo'     => 'MKZ-'.random_int(100000, 999999),
                'makbuzID'      => random_int(1000000, 9999999),
                'seriNo'        => 'K',
                'makbuzNo'      => random_int(100, 999),
                'receipt'       => [
                    'toplamTutarYazi' => 'DEMO ÖDEME',
                    'toplamTutar'     => (float) collect($debts)->whereIn('id', $debtIds)->sum('amount'),
                ],
            ];
        }

        $selectedDebts = collect($debts)->whereIn('id', $debtIds)->values()->all();

        return $this->withSessionRetry(fn () => $this->tahsilat->confirmBankPayment(
            $citizen['gensicilNo'] ?? $citizen['sicilNo'] ?? $identityNo,
            $selectedDebts,
            $transactionId,
            $citizen,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveCitizenForPayment(
        string $identityNo,
        ?string $searchType,
        ?string $gensicilNo,
        ?string $aboneNo = null,
    ): array {
        $citizen = $this->getCitizen($identityNo, $searchType);

        if (! empty($gensicilNo) && ! empty($citizen['accounts']) && is_array($citizen['accounts'])) {
            foreach ($citizen['accounts'] as $account) {
                $gensicilMatch = (string) ($account['gensicilNo'] ?? '') === (string) $gensicilNo;
                $aboneMatch = $aboneNo === null || $aboneNo === ''
                    || (string) ($account['aboneNo'] ?? '') === (string) $aboneNo;

                if ($gensicilMatch && $aboneMatch) {
                    return array_merge($citizen, [
                        'gensicilNo'     => (string) $account['gensicilNo'],
                        'sicilNo'        => (string) ($account['sicilNo'] ?? $account['gensicilNo']),
                        'aboneNo'        => (string) ($account['aboneNo'] ?? ''),
                        'fullName'       => (string) ($account['fullName'] ?? $citizen['fullName'] ?? ''),
                        'address'        => (string) ($account['address'] ?? ''),
                        'adi'            => (string) ($account['adi'] ?? ''),
                        'soyadi'         => (string) ($account['soyadi'] ?? ''),
                        'needsSelection' => false,
                        'accountKey'     => (string) ($account['accountKey'] ?? ''),
                    ]);
                }
            }
        }

        if (! empty($citizen['needsSelection']) && empty($gensicilNo)) {
            throw new BelsisException('Bu T.C. için birden fazla abonelik bulundu. Lütfen abonelik seçiniz.');
        }

        return $citizen;
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    private function withSessionRetry(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (BelsisException $e) {
            if ($this->shouldRetryWithFreshSession($e)) {
                $this->auth->forgetSession();

                return $callback();
            }
            throw $e;
        }
    }

    private function shouldUseMock(string $identityNo): bool
    {
        if (! config('belsis.mock')) {
            return false;
        }

        return in_array(trim($identityNo), config('belsis.mock_aboneler', []), true);
    }

    private function shouldRetryWithFreshSession(BelsisException $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return str_contains($message, 'oturum')
            || str_contains($message, 'session')
            || in_array($e->sonucKodu, ['1002', '1003', '401', '403'], true);
    }

    /**
     * @return array{identityNo: string, fullName: string, sicilNo: string, adi?: string, soyadi?: string}
     */
    private function mockCitizen(string $identityNo, ?string $searchType = null): array
    {
        $resolvedType = in_array($searchType, ['tc', 'sicil'], true)
            ? $searchType
            : (strlen(trim($identityNo)) === 11 ? 'tc' : 'sicil');

        return [
            'sicilNo'    => $identityNo,
            'identityNo' => $identityNo,
            'gensicilNo' => $identityNo,
            'fullName'   => 'Ahmet YILMAZ (Demo)',
            'searchType' => $resolvedType,
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
