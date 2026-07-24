<?php

namespace App\Http\Controllers;

use App\Services\Kiosk\KioskDailyCounter;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class KioskReportController extends Controller
{
    public function __invoke(Request $request, KioskDailyCounter $counter): View
    {
        $configuredKey = trim((string) config('kiosk.report_key'));
        if ($configuredKey !== '') {
            $given = (string) $request->query('key', $request->header('X-Report-Key', ''));
            if (! hash_equals($configuredKey, $given)) {
                throw new AccessDeniedHttpException('Rapor için geçerli anahtar gerekli.');
            }
        }

        $days = (int) $request->query('days', 30);
        $kioskId = $request->query('kiosk_id');
        $kioskId = is_string($kioskId) && $kioskId !== '' ? $kioskId : null;

        $rows = $counter->report($kioskId, $days);
        $today = $counter->today($kioskId);

        return view('kiosk.report', [
            'rows' => $rows,
            'today' => $today,
            'days' => max(1, min(90, $days)),
            'kioskId' => $kioskId ?: 'tümü',
            'supportPhone' => config('kiosk.support_phone'),
        ]);
    }
}
