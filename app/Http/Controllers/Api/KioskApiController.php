<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BelsisException;
use App\Http\Controllers\Controller;
use App\Services\Belsis\BelsisKioskService;
use App\Services\Belsis\WaterCardKioskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KioskApiController extends Controller
{
    public function __construct(
        private readonly BelsisKioskService $belsis,
        private readonly WaterCardKioskService $waterCard,
    ) {}

    public function citizen(Request $request, string $identityNo): JsonResponse
    {
        try {
            $this->assertAboneNo($identityNo);
            $searchType = $this->resolveSearchType($request);

            return response()->json($this->belsis->getCitizen($identityNo, $searchType));
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function debts(Request $request, string $identityNo): JsonResponse
    {
        try {
            $this->assertAboneNo($identityNo);
            $searchType = $this->resolveSearchType($request);

            return response()->json($this->belsis->getDebts($identityNo, $searchType));
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function paymentMethods(): JsonResponse
    {
        try {
            return response()->json($this->belsis->getPaymentMethods());
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function receipt(Request $request, int $makbuzId): JsonResponse
    {
        $validated = $request->validate([
            'seriNo'   => 'nullable|string',
            'makbuzNo' => 'nullable|integer',
        ]);

        try {
            return response()->json($this->belsis->getReceipt(
                $makbuzId,
                $validated['seriNo'] ?? null,
                isset($validated['makbuzNo']) ? (int) $validated['makbuzNo'] : null,
            ));
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function initiatePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identityNo' => 'required|string|regex:/^\d{1,10}$/',
            'debtIds'    => 'required|array|min:1',
            'debtIds.*'  => 'required|string',
            'searchType' => 'nullable|in:abone,sicil',
        ]);

        try {
            return response()->json(
                $this->belsis->initiatePayment(
                    $validated['identityNo'],
                    $validated['debtIds'],
                    $validated['searchType'] ?? 'abone',
                ),
            );
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function paymentStatus(Request $request, string $transactionId): JsonResponse
    {
        $validated = $request->validate([
            'identityNo' => 'required|string|regex:/^\d{1,10}$/',
            'debtIds'    => 'required|array|min:1',
            'debtIds.*'  => 'required|string',
            'searchType' => 'nullable|in:abone,sicil',
        ]);

        try {
            return response()->json(
                $this->belsis->confirmPayment(
                    $validated['identityNo'],
                    $validated['debtIds'],
                    $transactionId,
                    $validated['searchType'] ?? 'abone',
                ),
            );
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function waterCardRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor'  => 'required|in:baylan,metlab',
            'aboneNo' => 'nullable|string|regex:/^\d+$/',
        ]);

        try {
            return response()->json(
                $this->waterCard->readCard($validated['vendor'], $validated['aboneNo'] ?? null),
            );
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function waterSubscriber(string $vendor, string $aboneNo): JsonResponse
    {
        try {
            return response()->json($this->waterCard->getSubscriber($vendor, $aboneNo));
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function waterInvoices(string $vendor, string $aboneNo): JsonResponse
    {
        try {
            return response()->json($this->waterCard->getInvoices($vendor, $aboneNo));
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function waterCalculateKontor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor'  => 'required|in:baylan,metlab',
            'aboneNo' => 'required|string|regex:/^\d+$/',
            'tons'    => 'required|integer|min:1',
        ]);

        try {
            return response()->json(
                $this->waterCard->calculateKontor($validated['vendor'], $validated['aboneNo'], $validated['tons']),
            );
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function waterPayInvoices(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor'     => 'required|in:baylan,metlab',
            'aboneNo'    => 'required|string|regex:/^\d+$/',
            'invoiceIds' => 'required|array|min:1',
            'invoiceIds.*' => 'required|string',
        ]);

        try {
            return response()->json(
                $this->waterCard->payInvoices($validated['vendor'], $validated['aboneNo'], $validated['invoiceIds']),
            );
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function waterConfirmInvoicePayment(Request $request, string $transactionId): JsonResponse
    {
        $validated = $request->validate([
            'vendor'     => 'required|in:baylan,metlab',
            'aboneNo'    => 'required|string|regex:/^\d+$/',
            'invoiceIds' => 'required|array|min:1',
            'invoiceIds.*' => 'required|string',
        ]);

        try {
            return response()->json(
                $this->waterCard->confirmInvoicePayment(
                    $validated['vendor'],
                    $validated['aboneNo'],
                    $transactionId,
                    $validated['invoiceIds'],
                ),
            );
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function waterAdvanceLoad(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor'  => 'required|in:baylan,metlab',
            'aboneNo' => 'required|string|regex:/^\d+$/',
        ]);

        try {
            return response()->json(
                $this->waterCard->loadAdvance($validated['vendor'], $validated['aboneNo']),
            );
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function waterInitiateKontor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor'  => 'required|in:baylan,metlab',
            'aboneNo' => 'required|string|regex:/^\d+$/',
            'tons'    => 'required|integer|min:1',
        ]);

        try {
            return response()->json(
                $this->waterCard->initiateKontorPayment(
                    $validated['vendor'],
                    $validated['aboneNo'],
                    $validated['tons'],
                ),
            );
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function waterConfirmKontor(Request $request, string $transactionId): JsonResponse
    {
        $validated = $request->validate([
            'vendor'  => 'required|in:baylan,metlab',
            'aboneNo' => 'required|string|regex:/^\d+$/',
            'tons'    => 'required|integer|min:1',
        ]);

        try {
            return response()->json(
                $this->waterCard->confirmKontorPayment(
                    $validated['vendor'],
                    $validated['aboneNo'],
                    $transactionId,
                    $validated['tons'],
                ),
            );
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function waterKontorOptions(): JsonResponse
    {
        return response()->json(['options' => $this->waterCard->kontorOptions()]);
    }

    private function belsisError(BelsisException $e): JsonResponse
    {
        $message = mb_strtolower($e->getMessage());
        $status = 422;

        if (str_contains($message, '11 haneli') || str_contains($message, 'abone numarası')) {
            $status = 400;
        } elseif (
            str_contains($message, 'bulunamad')
            || str_contains($message, 'kayıt yok')
            || str_contains($message, 'kayit yok')
            || str_contains($message, 'sonuç boş')
            || str_contains($message, 'sonuc bos')
            || str_contains($message, 'eşleştirilemedi')
            || str_contains($message, 'eslestirilemedi')
        ) {
            $status = 404;
        } elseif (
            $e->sonucKodu === '1004'
            || str_contains($message, 'online tahsilatta görüntülenecek borç yok')
        ) {
            $status = 404;
        } elseif (
            str_contains($message, 'yetkisiz')
            || str_contains($message, 'bağlanılamadı')
            || str_contains($message, 'baglanilamadi')
            || str_contains($message, 'sistem hatas')
            || str_contains($message, 'oturum')
            || str_contains($message, 'ip adresini tanımıyor')
        ) {
            $status = 503;
        }

        return response()->json([
            'message'   => $e->getMessage(),
            'sonucKodu' => $e->sonucKodu,
        ], $status);
    }

    private function assertAboneNo(string $aboneNo): void
    {
        $aboneNo = trim($aboneNo);

        if (! ctype_digit($aboneNo) || strlen($aboneNo) < 1 || strlen($aboneNo) > 10) {
            throw new BelsisException('Abone/Sicil numarası 1–10 haneli olmalıdır.');
        }
    }

    /**
     * @return 'abone'|'sicil'
     */
    private function resolveSearchType(Request $request): string
    {
        $validated = $request->validate(['searchType' => 'nullable|in:abone,sicil']);

        return $validated['searchType'] ?? 'abone';
    }
}
