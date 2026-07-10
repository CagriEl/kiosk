<?php

namespace App\Services\Belsis\Concerns;

use App\Exceptions\BelsisException;

trait ChecksBelsisInfrastructureErrors
{
    /**
     * Oturum/bağlantı düzeyindeki hataları "kayıt bulunamadı" gibi iş hatalarından ayırır —
     * bu tür hatalar yutulmamalı, çağırana iletilmelidir.
     */
    private function isInfrastructureError(BelsisException $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return str_contains($message, 'yetkisiz_ip')
            || str_contains($message, 'bağlanılamadı')
            || str_contains($message, 'baglanilamadi')
            || str_contains($message, 'html')
            || str_contains($message, 'oturum')
            || str_contains($message, 'ip adresini tanımıyor')
            || in_array($e->sonucKodu, ['401', '403', '1002', '1003'], true);
    }

    /**
     * borcSorgula/arama gibi sorguTip parametresi kuruma göre değişen metotlarda, SP'nin
     * o tip için hiç çalışmadığını gösteren hata sınıfı ("Sistem Hatası — ExecuteReader:
     * CommandText property has not been initialized"). Bu alınırsa denenen sorguTip'in
     * kendisi değil, SP'nin o tip için dispatch etmemesi sorumludur — kalan sorguTip
     * kombinasyonlarını denemeye devam etmenin anlamı yoktur, bir sonraki veri
     * kaynağına geçilmelidir.
     */
    private function isSystemicBorcError(BelsisException $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return str_contains($message, 'commandtext')
            || str_contains($message, 'command text')
            || str_contains($message, 'not been initialized')
            || str_contains($message, 'sistem hatas');
    }
}
