<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKioskApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = trim((string) config('kiosk.api_key', ''));
        if ($configured === '') {
            return $next($request);
        }

        $provided = (string) ($request->header('X-Kiosk-Key') ?? $request->query('kioskKey') ?? '');
        if (! hash_equals($configured, $provided)) {
            return response()->json([
                'message' => 'Yetkisiz kiosk erişimi.',
                'sonucKodu' => '401',
            ], 401);
        }

        return $next($request);
    }
}
