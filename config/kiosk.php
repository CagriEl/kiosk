<?php

return [
    // Her fiziksel kiosk cihazı için benzersiz kimlik (log + rate limit)
    'id' => env('KIOSK_ID', 'kiosk-1'),

    // Boş bırakılırsa API key kontrolü yapılmaz. Doluysa X-Kiosk-Key zorunlu.
    'api_key' => env('KIOSK_API_KEY', ''),

    'support_phone' => env('KIOSK_SUPPORT_PHONE', '444 01 39'),

    // Yerel test sorgusu butonu — varsayılan kapalı
    'enable_test_query' => filter_var(env('KIOSK_ENABLE_TEST_QUERY', false), FILTER_VALIDATE_BOOLEAN),

    // Doğum tarihi / sorgu deneme limiti
    'rate_limit' => [
        'max_attempts' => (int) env('KIOSK_RATE_LIMIT_ATTEMPTS', 5),
        'decay_seconds' => (int) env('KIOSK_RATE_LIMIT_DECAY', 600), // 10 dk
    ],

    // Başarılı doğrulama sonrası borç sorgusu için token ömrü (sn)
    'query_token_ttl' => (int) env('KIOSK_QUERY_TOKEN_TTL', 900), // 15 dk

    // Sağlık kontrolü — Belsis oturum açmayı dener (hafif)
    'health_check_belsis' => filter_var(env('KIOSK_HEALTH_CHECK_BELSIS', true), FILTER_VALIDATE_BOOLEAN),

    // Gece bakımı — bilgisayar yerel saatine göre tam ekran (00:44 uyarı → 00:45–07:00 kilit)
    'maintenance' => [
        'warn_hour' => (int) env('KIOSK_MAINT_WARN_HOUR', 0),
        'warn_minute' => (int) env('KIOSK_MAINT_WARN_MINUTE', 44),
        'start_hour' => (int) env('KIOSK_MAINT_START_HOUR', 0),
        'start_minute' => (int) env('KIOSK_MAINT_START_MINUTE', 45),
        'end_hour' => (int) env('KIOSK_MAINT_END_HOUR', 7),
        'end_minute' => (int) env('KIOSK_MAINT_END_MINUTE', 0),
    ],

    // Günlük rapor sayfası: /rapor?key=...  (boşsa anahtar istenmez — yalnızca iç ağda kullanın)
    'report_key' => env('KIOSK_REPORT_KEY', ''),
];
