<?php

namespace App\Services\Kiosk;

use App\Models\KioskDailyStat;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class KioskDailyCounter
{
    public function increment(string $metric, ?string $kioskId = null, ?CarbonInterface $day = null): void
    {
        if (! in_array($metric, [
            KioskDailyStat::METRIC_DEBT_QUERY,
            KioskDailyStat::METRIC_AVANS_CREDIT,
        ], true)) {
            return;
        }

        $kioskId = $kioskId ?: (string) config('kiosk.id', 'kiosk-1');
        $date = ($day ?? now())->toDateString();

        try {
            $row = KioskDailyStat::query()->firstOrCreate(
                [
                    'stat_date' => $date,
                    'kiosk_id' => $kioskId,
                    'metric' => $metric,
                ],
                ['count' => 0],
            );
            $row->increment('count');
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return list<array{date: string, debt_query: int, avans_credit: int, total: int}>
     */
    public function report(?string $kioskId = null, int $days = 30): array
    {
        $days = max(1, min(90, $days));
        $from = now()->subDays($days - 1)->startOfDay();
        $to = now()->endOfDay();

        $query = KioskDailyStat::query()
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()]);

        if ($kioskId) {
            $query->where('kiosk_id', $kioskId);
        }

        /** @var Collection<string, Collection<int, KioskDailyStat>> $grouped */
        $grouped = $query->get()->groupBy(fn (KioskDailyStat $row) => $row->stat_date->toDateString());

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i)->toDateString();
            $rows = $grouped->get($date, collect());
            $debt = (int) $rows->where('metric', KioskDailyStat::METRIC_DEBT_QUERY)->sum('count');
            $avans = (int) $rows->where('metric', KioskDailyStat::METRIC_AVANS_CREDIT)->sum('count');
            $out[] = [
                'date' => $date,
                'debt_query' => $debt,
                'avans_credit' => $avans,
                'total' => $debt + $avans,
            ];
        }

        return array_reverse($out);
    }

    /**
     * @return array{date: string, debt_query: int, avans_credit: int, total: int}
     */
    public function today(?string $kioskId = null): array
    {
        $today = Carbon::today()->toDateString();
        $rows = collect($this->report($kioskId, 1));

        return $rows->first() ?? [
            'date' => $today,
            'debt_query' => 0,
            'avans_credit' => 0,
            'total' => 0,
        ];
    }
}
