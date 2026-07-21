<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BelsisException;
use App\Http\Controllers\Controller;
use App\Services\Belsis\BelsisAuthService;
use App\Services\Belsis\BelsisKioskService;
use App\Services\Belsis\WaterCardKioskService;
use App\Services\Kiosk\KioskAuditLogger;
use App\Services\Kiosk\KioskQueryGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class KioskApiController extends Controller
{
    public function __construct(
        private readonly BelsisKioskService $belsis,
        private readonly WaterCardKioskService $waterCard,
        private readonly BelsisAuthService $belsisAuth,
        private readonly KioskQueryGate $queryGate,
        private readonly KioskAuditLogger $audit,
    ) {}

    public function health(): JsonResponse
    {
        $kioskId = $this->queryGate->kioskId(request()->header('X-Kiosk-Id'));
        $payload = [
            'status' => 'ok',
            'kiosk_id' => $kioskId,
            'support_phone' => config('kiosk.support_phone'),
            'belsis' => 'ok',
            'message' => null,
        ];

        if (config('belsis.mock')) {
            $payload['belsis'] = 'mock';

            return response()->json($payload);
        }

        if (! config('kiosk.health_check_belsis', true)) {
            $payload['belsis'] = 'skipped';

            return response()->json($payload);
        }

        try {
            $this->belsisAuth->getSession();
        } catch (Throwable $e) {
            $this->audit->log('health_check', false, null, $kioskId, $e->getMessage());

            return response()->json([
                'status' => 'degraded',
                'kiosk_id' => $kioskId,
                'support_phone' => config('kiosk.support_phone'),
                'belsis' => 'down',
                'message' => 'Belediye ödeme sistemi şu an kullanılamıyor. Lütfen '.config('kiosk.support_phone').' nolu hattı arayınız.',
            ], 503);
        }

        return response()->json($payload);
    }

    public function citizen(Request $request, string $identityNo): JsonResponse
    {
        $kioskId = $this->queryGate->kioskId($request->header('X-Kiosk-Id'));

        try {
            $this->assertTcKimlikNo($identityNo);
            $this->queryGate->assertNotRateLimited($kioskId, $identityNo);

            $birthDate = $request->query('birthDate', $request->input('birthDate'));
            $birthDate = is_string($birthDate) ? trim($birthDate) : null;

            $citizen = $this->belsis->getCitizen($identityNo, 'tc', $birthDate);
            $queryToken = $this->queryGate->recordSuccess($kioskId, $identityNo);
            $citizen['queryToken'] = $queryToken;

            return response()->json($citizen);
        } catch (BelsisException $e) {
            if ($this->isVerificationFailure($e)) {
                $this->queryGate->recordFailure($kioskId, $identityNo, $e->getMessage());
            } else {
                $this->audit->log('citizen_query', false, $identityNo, $kioskId, $e->getMessage());
            }

            return $this->belsisError($e);
        } catch (Throwable $e) {
            report($e);
            $this->audit->log('citizen_query', false, $identityNo, $kioskId, $e->getMessage());

            return $this->belsisError(new BelsisException(
                'Sorgulama sırasında beklenmeyen bir hata oluştu. Lütfen tekrar deneyiniz.',
            ));
        }
    }

    public function sicilDetay(Request $request, string $sicilNo): JsonResponse
    {
        try {
            $this->assertSicilNo($sicilNo);

            return response()->json($this->belsis->getSicilDetay($sicilNo));
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function debts(Request $request, string $identityNo): JsonResponse
    {
        $kioskId = $this->queryGate->kioskId($request->header('X-Kiosk-Id'));

        try {
            $this->assertTcKimlikNo($identityNo);

            $queryToken = $request->query('queryToken', $request->header('X-Query-Token'));
            $queryToken = is_string($queryToken) ? trim($queryToken) : '';
            $this->queryGate->assertQueryToken($queryToken, $identityNo, $kioskId);

            $gensicilNo = $request->query('gensicilNo');
            $gensicilNo = is_string($gensicilNo) ? trim($gensicilNo) : null;
            if ($gensicilNo !== null && ($gensicilNo === '' || ! ctype_digit($gensicilNo))) {
                throw new BelsisException('Geçersiz abonelik numarası.');
            }

            $aboneNo = $request->query('aboneNo');
            $aboneNo = is_string($aboneNo) ? trim($aboneNo) : null;
            if ($aboneNo !== null && $aboneNo !== '' && ! ctype_digit($aboneNo)) {
                throw new BelsisException('Geçersiz abone numarası.');
            }

            return response()->json($this->belsis->getDebts(
                $identityNo,
                'tc',
                $gensicilNo,
                ($aboneNo !== null && $aboneNo !== '') ? $aboneNo : null,
            ));
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        } catch (Throwable $e) {
            report($e);

            return $this->belsisError(new BelsisException(
                'Borç sorgusu sırasında beklenmeyen bir hata oluştu. Lütfen tekrar deneyiniz.',
            ));
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
            'identityNo' => 'required|string|regex:/^\d{11}$/',
            'gensicilNo' => 'nullable|string|regex:/^\d{1,10}$/',
            'aboneNo'    => 'nullable|string|regex:/^\d{1,20}$/',
            'debtIds'    => 'required|array|min:1',
            'debtIds.*'  => 'required|string',
        ]);

        try {
            $this->assertTcKimlikNo($validated['identityNo']);

            return response()->json(
                $this->belsis->initiatePayment(
                    $validated['identityNo'],
                    $validated['debtIds'],
                    'tc',
                    $validated['gensicilNo'] ?? null,
                    $validated['aboneNo'] ?? null,
                ),
            );
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    public function paymentStatus(Request $request, string $transactionId): JsonResponse
    {
        $validated = $request->validate([
            'identityNo' => 'required|string|regex:/^\d{11}$/',
            'gensicilNo' => 'nullable|string|regex:/^\d{1,10}$/',
            'aboneNo'    => 'nullable|string|regex:/^\d{1,20}$/',
            'debtIds'    => 'required|array|min:1',
            'debtIds.*'  => 'required|string',
        ]);

        try {
            $this->assertTcKimlikNo($validated['identityNo']);

            return response()->json(
                $this->belsis->confirmPayment(
                    $validated['identityNo'],
                    $validated['debtIds'],
                    $transactionId,
                    'tc',
                    $validated['gensicilNo'] ?? null,
                    $validated['aboneNo'] ?? null,
                ),
            );
        } catch (BelsisException $e) {
            return $this->belsisError($e);
        }
    }

    /**
     * Baylan ASPX sayfasını Windows kiosk üzerinde Edge IE modunda açar.
     */
    public function openBaylan(): JsonResponse
    {
        $url = trim((string) config('belsis.baylan_ie_url'));
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['message' => 'Baylan adresi yapılandırılmamış.'], 500);
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return response()->json([
                'ok'      => false,
                'opened'  => false,
                'url'     => $url,
                'message' => 'Edge IE modu yalnızca Windows kiosk üzerinde açılır.',
            ]);
        }

        try {
            $this->launchEdgeIeMode($url);

            return response()->json(['ok' => true, 'opened' => true, 'url' => $url]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'ok'      => false,
                'opened'  => false,
                'url'     => $url,
                'message' => 'Edge IE modunda açılamadı. Lütfen kiosk bilgisayarını kontrol ediniz.',
            ], 500);
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
        $rawMessage = $e->getMessage();
        $message = mb_strtolower($rawMessage);
        $status = 422;

        // Sicil bulunduktan sonra tahakkuk oturumu açılamazsa kullanıcıya teknik
        // "oturum kimliği" yerine borç bulunamadı mesajı göster.
        if (
            str_contains($message, 'oturum kimliği')
            || str_contains($message, 'oturum kimligi')
        ) {
            $rawMessage = 'Sicil kaydınız bulundu ancak ödenecek borç bulunamadı.';
            $status = 404;
        } elseif (
            str_contains($message, '11 haneli olmalıdır')
            || str_contains($message, '1–10 haneli')
            || str_contains($message, '1-10 haneli')
            || str_contains($message, 'geçersiz sicil')
            || str_contains($message, 'geçersiz abone')
        ) {
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
            str_contains($message, 'çok fazla başarısız')
            || str_contains($message, 'cok fazla basarisiz')
        ) {
            $status = 429;
        } elseif (
            str_contains($message, 'oturum doğrulaması')
            || str_contains($message, 'doğrulama süresi')
            || str_contains($message, 'doğrulama geçersiz')
            || str_contains($message, 'dogurlama suresi')
            || str_contains($message, 'dogurlama gecersiz')
        ) {
            $status = 401;
        } elseif (
            str_contains($message, 'yetkisiz')
            || str_contains($message, 'bağlanılamadı')
            || str_contains($message, 'baglanilamadi')
            || str_contains($message, 'ulaşılamadı')
            || str_contains($message, 'ulasilamadi')
            || str_contains($message, 'zaman aşımı')
            || str_contains($message, 'zaman asimi')
            || str_contains($message, 'dns:')
            || str_contains($message, 'sistem hatas')
            || str_contains($message, 'oturum')
            || str_contains($message, 'ip adresini tanımıyor')
        ) {
            $status = 503;
        }

        return response()->json([
            'message'   => $rawMessage,
            'sonucKodu' => $e->sonucKodu,
        ], $status);
    }

    private function isVerificationFailure(BelsisException $e): bool
    {
        $message = mb_strtolower($e->getMessage(), 'UTF-8');

        return str_contains($message, 'doğum tarihi')
            || str_contains($message, 'dogum tarihi')
            || str_contains($message, 'çok fazla başarısız')
            || str_contains($message, 'cok fazla basarisiz');
    }

    private function assertTcKimlikNo(string $identityNo): void
    {
        $identityNo = trim($identityNo);

        if (! ctype_digit($identityNo) || strlen($identityNo) !== 11) {
            throw new BelsisException('T.C. Kimlik No 11 haneli olmalıdır.');
        }
    }

    private function assertSicilNo(string $sicilNo): void
    {
        $sicilNo = trim($sicilNo);

        if (! ctype_digit($sicilNo) || strlen($sicilNo) < 1 || strlen($sicilNo) > 10) {
            throw new BelsisException('Sicil numarası 1–10 haneli olmalıdır.');
        }
    }

    private function launchEdgeIeMode(string $url): void
    {
        $edge = $this->resolveEdgePath();
        $cmd = 'cmd /c start "" '.escapeshellarg($edge)
            .' --edge-kiosk-type=fullscreen --ie-mode-force --no-first-run'
            .' --disable-pinch --overscroll-history-navigation=0'
            .' --kiosk '.escapeshellarg($url);

        $process = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (! is_resource($process)) {
            throw new \RuntimeException('Edge süreci başlatılamadı.');
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($process);
    }

    private function resolveEdgePath(): string
    {
        $candidates = [
            (string) getenv('ProgramFiles(x86)').'\\Microsoft\\Edge\\Application\\msedge.exe',
            (string) getenv('ProgramFiles').'\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        ];

        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return 'msedge';
    }
}
