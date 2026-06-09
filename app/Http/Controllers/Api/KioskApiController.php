<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BelsisException;
use App\Http\Controllers\Controller;
use App\Services\Belsis\BelsisKioskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KioskApiController extends Controller
{
    public function __construct(
        private readonly BelsisKioskService $belsis,
    ) {}

    public function citizen(string $identityNo): JsonResponse
    {
        try {
            return response()->json($this->belsis->getCitizen($identityNo));
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function debts(string $identityNo): JsonResponse
    {
        try {
            return response()->json($this->belsis->getDebts($identityNo));
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function initiatePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identityNo' => 'required|string|min:5|regex:/^\d+$/',
            'debtIds'    => 'required|array|min:1',
            'debtIds.*'  => 'required|string',
        ]);

        try {
            return response()->json(
                $this->belsis->initiatePayment($validated['identityNo'], $validated['debtIds']),
            );
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function paymentStatus(Request $request, string $transactionId): JsonResponse
    {
        $validated = $request->validate([
            'identityNo' => 'required|string|min:5|regex:/^\d+$/',
            'debtIds'    => 'required|array|min:1',
            'debtIds.*'  => 'required|string',
        ]);

        try {
            return response()->json(
                $this->belsis->confirmPayment(
                    $validated['identityNo'],
                    $validated['debtIds'],
                    $transactionId,
                ),
            );
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    private function belsisError(BelsisException $e): JsonResponse
    {
        return response()->json([
            'message'   => $e->getMessage(),
            'sonucKodu' => $e->sonucKodu,
        ], 422);
    }
}
