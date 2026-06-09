<?php

return [
    'username' => env('BELSIS_USERNAME', 'sa'),
    'password' => env('BELSIS_PASSWORD', ''),

    'tahakkuk_url' => env('BELSIS_TAHAKKUK_URL', 'http://aykome.belsis.uygulama.belsis.com.tr/tahakkukWebServis.asmx'),
    'tahsilat_url' => env('BELSIS_TAHSILAT_URL', 'http://aykome.belsis.uygulama.belsis.com.tr/tahsilatWebServis.asmx'),

    'ip_address' => env('BELSIS_IP_ADDRESS', '127.0.0.1'),
    'timeout'    => (int) env('BELSIS_TIMEOUT', 30),
    'verify_ssl' => filter_var(env('BELSIS_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN),

    'session_cache_ttl' => (int) env('BELSIS_SESSION_TTL', 1500),

    'mock' => filter_var(env('BELSIS_MOCK', false), FILTER_VALIDATE_BOOLEAN),

    // Mock yalnızca bu sicil numaraları için kullanılır (gerçek TC her zaman Belsis'e gider)
    'mock_sicils' => array_filter(array_map('trim', explode(',', env('BELSIS_MOCK_SICILS', '89874')))),

    'namespace' => 'http://tempuri.org/',
];
