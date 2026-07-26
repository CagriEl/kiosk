<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KioskDailyStat extends Model
{
    public const METRIC_DEBT_QUERY = 'debt_query';

    public const METRIC_AVANS_CREDIT = 'avans_credit';

    protected $fillable = [
        'stat_date',
        'kiosk_id',
        'metric',
        'count',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'count' => 'integer',
        ];
    }
}
