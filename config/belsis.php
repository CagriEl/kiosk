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

    // Mock yalnızca bu abone numaraları için kullanılır
    'mock_aboneler' => array_filter(array_map('trim', explode(',', env(
        'BELSIS_MOCK_ABONELER',
        env('BELSIS_MOCK_SICILS', '89874'),
    )))),

    // arama methodu Kırklareli kurulumunda tüm sorguTip değerleri için sunucu tarafında
    // hata veriyor ("Sistem Hatası — ExecuteReader: CommandText property has not been
    // initialized") — client tarafında düzeltilemeyen bir SP/dispatch hatası. Devre dışı
    // bırakılınca borcSorgula/sicilSorgula tabanlı yedek yollara doğrudan geçilir, her
    // sorguda 5 boşuna round-trip harcanmaz. Belsis IT tarafında düzeltilirse true yapılabilir.
    'arama_enabled' => filter_var(env('BELSIS_ARAMA_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // TC → gensicil arama (arama methodu — Kırklareli: 2=TCKN)
    'arama_sorgu_tips' => array_filter(array_map('trim', explode(',', env(
        'BELSIS_ARAMA_SORGU_TIPS',
        '2,TC,TcKimlikNo,TCKIMLIK,Tc',
    )))),

    // Abone no → gensicil arama (tahsilat arama — WSDL: sorguTip + sorguNo)
    'arama_sorgu_tips_abone' => array_filter(array_map('trim', explode(',', env(
        'BELSIS_ARAMA_SORGU_TIPS_ABONE',
        'UYE,UYENO,ABONE,MUKELLEF,1',
    )))),

    // @deprecated — BELSIS_ARAMA_SORGU_TIPS_ABONE kullanın
    'arama_sorgu_tips_sicil' => array_filter(array_map('trim', explode(',', env(
        'BELSIS_ARAMA_SORGU_TIPS_SICIL',
        'UYE,UYENO,ABONE,MUKELLEF,1',
    )))),

    // Gensicilno/sicil no'ya özgü arama sorguTip tahminleri — abone/TC tiplerinden ayrı,
    // Kırklareli SP'sinin "sicil" türü için farklı bir dispatch kolu olma ihtimaline karşı denenir.
    'arama_sorgu_tips_gensicil' => array_filter(array_map('trim', explode(',', env(
        'BELSIS_ARAMA_SORGU_TIPS_GENSICIL',
        'SICIL,SICILNO,GENSICIL,GENSICILNO,3,0',
    )))),

    // borcSorgula — onlinetahsilatborcsoruglama SP @sorgutip zorunlu (kuruma göre değişir)
    'borc_sorgu_tip_abone' => env('BELSIS_BORC_SORGU_TIP_ABONE', env('BELSIS_BORC_SORGU_TIP_SICIL', '1')),
    'borc_sorgu_tip_tc'    => env('BELSIS_BORC_SORGU_TIP_TC', '2'),
    'borc_sorgu_tips_abone' => array_filter(array_map('trim', explode(',', env(
        'BELSIS_BORC_SORGU_TIPS_ABONE',
        'UYE,UYENO,ABONE,MUKELLEF,1',
    )))),
    // @deprecated — BELSIS_BORC_SORGU_TIPS_ABONE kullanın
    'borc_sorgu_tip_sicil' => env('BELSIS_BORC_SORGU_TIP_SICIL', '1'),
    'borc_sorgu_tips_sicil' => array_filter(array_map('trim', explode(',', env(
        'BELSIS_BORC_SORGU_TIPS_SICIL',
        'UYE,UYENO,ABONE,MUKELLEF,1',
    )))),
    'borc_sorgu_tips_tc' => array_filter(array_map('trim', explode(',', env(
        'BELSIS_BORC_SORGU_TIPS_TC',
        '2,TC,TcKimlikNo,TCKIMLIK,Tc',
    )))),

    // Gensicilno/sicil no'ya özgü borcSorgula sorguTip tahminleri (yalnızca gensicilno ile
    // çalışan tahsilat sorgu akışı için — bkz. arama_sorgu_tips_gensicil ile aynı mantık).
    'borc_sorgu_tips_gensicil' => array_filter(array_map('trim', explode(',', env(
        'BELSIS_BORC_SORGU_TIPS_GENSICIL',
        'SICIL,SICILNO,GENSICIL,GENSICILNO,UYE,UYENO,ABONE,MUKELLEF,1,3,0',
    )))),

    // borcSorgula / arama — 1004: kayıt var, online borç yok veya kısmi yanıt
    'soft_success_codes' => array_filter(array_map('trim', explode(',', env(
        'BELSIS_SOFT_SUCCESS_CODES',
        '1004',
    )))),
    'soft_success_methods' => ['borcSorgula', 'arama'],

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
