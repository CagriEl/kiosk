<?php

namespace Tests\Feature;

use App\Exceptions\BelsisException;
use App\Services\Belsis\BelsisSoapClient;
use App\Services\Belsis\BelsisTahsilatQueryService;
use Mockery;
use Tests\TestCase;

/**
 * Prod'da gözlemlenen senaryo: tahsilat tarafında borç yok (sicilBorcBeyanSorgula boş),
 * kod tahakkukWebServis'e (tahakkukBilgileriniGetir) düşüyor — bu çağrı bazı sicil
 * türleri için (ör. tüzel kişi) iş hatası döndürebiliyor (ör. kod 1101). Bu durum
 * tüm sorguyu patlatmamalı, "borç yok" olarak ele alınmalı.
 */
class BelsisTahakkukFallbackTest extends TestCase
{
    public function test_non_infrastructure_error_from_tahakkuk_fallback_is_treated_as_no_debts(): void
    {
        $soap = Mockery::mock(BelsisSoapClient::class);

        $soap->shouldReceive('callTahsilat')
            ->with('login', Mockery::any(), Mockery::any())
            ->andReturn(['oturumKimligi' => 'session-1', 'guvenlikKodu' => 'g', 'seriNo' => 'K']);

        $soap->shouldReceive('callTahakkuk')
            ->with('login', Mockery::any(), Mockery::any())
            ->andReturn(['oturumKimligi' => 'session-2', 'guvenlikKodu' => 'g', 'seriNo' => 'K']);

        $soap->shouldReceive('callTahsilat')
            ->with('sicilBorcBeyanSorgula', Mockery::any())
            ->andReturn(['sicilBorcBeyanListesi' => []]);

        $soap->shouldReceive('callTahakkuk')
            ->with('tahakkukBilgileriniGetir', Mockery::any())
            ->andThrow(new BelsisException('Belsis işlemi başarısız (kod: 1101).', '1101'));

        $this->app->instance(BelsisSoapClient::class, $soap);

        $debts = $this->app->make(BelsisTahsilatQueryService::class)->getDebts('89874');

        $this->assertSame([], $debts);
    }

    public function test_infrastructure_error_from_tahakkuk_fallback_still_propagates(): void
    {
        $soap = Mockery::mock(BelsisSoapClient::class);

        $soap->shouldReceive('callTahsilat')
            ->with('login', Mockery::any(), Mockery::any())
            ->andReturn(['oturumKimligi' => 'session-1', 'guvenlikKodu' => 'g', 'seriNo' => 'K']);

        $soap->shouldReceive('callTahakkuk')
            ->with('login', Mockery::any(), Mockery::any())
            ->andReturn(['oturumKimligi' => 'session-2', 'guvenlikKodu' => 'g', 'seriNo' => 'K']);

        $soap->shouldReceive('callTahsilat')
            ->with('sicilBorcBeyanSorgula', Mockery::any())
            ->andReturn(['sicilBorcBeyanListesi' => []]);

        $soap->shouldReceive('callTahakkuk')
            ->with('tahakkukBilgileriniGetir', Mockery::any())
            ->andThrow(new BelsisException('Belsis sunucusu bu makinenin IP adresini tanımıyor (yetkisiz_ip).'));

        $this->app->instance(BelsisSoapClient::class, $soap);

        $this->expectException(BelsisException::class);
        $this->expectExceptionMessage('yetkisiz_ip');

        $this->app->make(BelsisTahsilatQueryService::class)->getDebts('89874');
    }
}
