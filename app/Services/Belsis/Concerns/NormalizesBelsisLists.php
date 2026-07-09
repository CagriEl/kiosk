<?php

namespace App\Services\Belsis\Concerns;

use Illuminate\Support\Arr;

trait NormalizesBelsisLists
{
    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeList(mixed $list): array
    {
        if (empty($list)) {
            return [];
        }

        if (is_array($list) && Arr::isAssoc($list) && ! isset($list[0])) {
            return [$list];
        }

        return array_values(array_filter(array_map(
            fn ($item) => is_array($item) ? $item : null,
            is_array($list) ? $list : [],
        )));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     */
    protected function pickString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! empty($data[$key])) {
                return (string) $data[$key];
            }
        }

        return null;
    }

    protected function normalizeDate(mixed $value): string
    {
        if (empty($value)) {
            return now()->toDateString();
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }
}
