<?php

return [
    'username' => env('BELSIS_USERNAME', 'sa'),
    'password' => env('BELSIS_PASSWORD', ''),

    'tahakkuk_url' => env('BELSIS_TAHAKKUK_URL', 'https://aykome.belsis.uygulama.belsis.com.tr/tahakkukWebServis.asmx'),
    'tahsilat_url' => env('BELSIS_TAHSILAT_URL', 'https://aykome.belsis.uygulama.belsis.com.tr/tahsilatWebServis.asmx'),

    // Sunucu IP'si veya "auto" (otomatik tespit)
    'ip_address' => env('BELSIS_IP_ADDRESS', 'auto'),
    'timeout'    => (int) env('BELSIS_TIMEOUT', 30),
    'verify_ssl' => filter_var(env('BELSIS_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN),

    'session_cache_ttl' => (int) env('BELSIS_SESSION_TTL', 1500),

    'mock' => filter_var(env('BELSIS_MOCK', false), FILTER_VALIDATE_BOOLEAN),

    // Mock yalnızca bu sicil numaraları için kullanılır (gerçek TC her zaman Belsis'e gider)
    'mock_sicils' => array_filter(array_map('trim', explode(',', env('BELSIS_MOCK_SICILS', '89874')))),

    // TC → sicil arama (arama methodu — Kırklareli: 2=TCKN)
    'arama_sorgu_tips' => array_filter(array_map('trim', explode(',', env(
        'BELSIS_ARAMA_SORGU_TIPS',
        '2,TC,TcKimlikNo,TCKIMLIK,Tc',
    )))),

    // Sicil / üye no → gensicil arama (arama methodu)
    'arama_sorgu_tips_sicil' => array_filter(array_map('trim', explode(',', env(
        'BELSIS_ARAMA_SORGU_TIPS_SICIL',
        '1,SICIL,GENSICIL,UYE,UYENO,MUKELLEF,Sicil,Gensicil',
    )))),

    // borcSorgula — onlinetahsilatborcsoruglama SP @sorgutip zorunlu (kuruma göre değişir)
    'borc_sorgu_tip_sicil' => env('BELSIS_BORC_SORGU_TIP_SICIL', '1'),
    'borc_sorgu_tip_tc'    => env('BELSIS_BORC_SORGU_TIP_TC', '2'),
    'borc_sorgu_tips_sicil' => array_filter(array_map('trim', explode(',', env(
        'BELSIS_BORC_SORGU_TIPS_SICIL',
        '1,SICIL,GENSICIL,GENSICILNO,Sicil,Gensicil',
    )))),
    'borc_sorgu_tips_tc' => array_filter(array_map('trim', explode(',', env(
        'BELSIS_BORC_SORGU_TIPS_TC',
        '2,TC,TcKimlikNo,TCKIMLIK,Tc',
    )))),

    // borcSorgula boş dönerse tahakkukWebServis'ten borç çek
    'tahakkuk_fallback' => filter_var(env('BELSIS_TAHAKKUK_FALLBACK', true), FILTER_VALIDATE_BOOLEAN),

    // 5 = Kredi Kartı (odemeSekilleri), 2 = Banka
    'odeme_sekli' => (int) env('BELSIS_ODEME_SEKLI', 5),

    'namespace' => 'http://tempuri.org/',

    // Kartlı su (Baylan / Metlab) — test modu
    'water_mock' => filter_var(env('BELSIS_WATER_MOCK', true), FILTER_VALIDATE_BOOLEAN),
    'water_mock_aboneler' => [
        'baylan' => array_filter(array_map('trim', explode(',', env('BELSIS_WATER_MOCK_BAYLAN', '12345,27126')))),
        'metlab' => array_filter(array_map('trim', explode(',', env('BELSIS_WATER_MOCK_METLAB', '67890,54321')))),
    ],
];
