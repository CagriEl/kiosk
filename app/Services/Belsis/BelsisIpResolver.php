<?php

namespace App\Services\Belsis;

class BelsisIpResolver
{
    public static function resolve(): string
    {
        $configured = trim((string) config('belsis.ip_address'));

        if ($configured !== '' && strtolower($configured) !== 'auto') {
            return $configured;
        }

        return self::detect();
    }

    public static function detect(): string
    {
        $candidates = [];

        if (! empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '127.0.0.1') {
            $candidates[] = $_SERVER['SERVER_ADDR'];
        }

        if (! empty($_SERVER['LOCAL_ADDR']) && $_SERVER['LOCAL_ADDR'] !== '127.0.0.1') {
            $candidates[] = $_SERVER['LOCAL_ADDR'];
        }

        $hostname = gethostname();
        if ($hostname) {
            $resolved = gethostbyname($hostname);
            if ($resolved && $resolved !== $hostname && $resolved !== '127.0.0.1') {
                $candidates[] = $resolved;
            }
        }

        return $candidates[0] ?? '127.0.0.1';
    }
}
