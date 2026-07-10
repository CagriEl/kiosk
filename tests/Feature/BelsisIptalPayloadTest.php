<?php

namespace Tests\Feature;

use App\Services\Belsis\BelsisSoapClient;
use App\Services\Belsis\BelsisTahakkukService;
use App\Services\Belsis\BelsisTahsilatService;
use Mockery;
use Tests\TestCase;

/**
 * makbuzIptal ve tahakkukIptal, WSDL'deki zorunlu alanları (iptalTarihi, kulno/aciklama)
 * göndermiyordu ve bazı alan adları yanlış case'teydi (SeriNo/MakbuzNo yerine seriNo/makbuzNo,
 * tahId yerine tahID) — WSDL şemasına göre bu alanlar minOccurs="1" (zorunlu). Bu testler,
 * gerçek SOAP çağrısına giden payload'ın WSDL'e uygun alan adlarını ve zorunlu alanları
 * içerdiğini doğrular.
 */
class BelsisIptalPayloadTest extends TestCase
{
    public function test_makbuz_iptal_sends_wsdl_required_fields(): void
    {
        $soap = Mockery::mock(BelsisSoapClient::class);

        $soap->shouldReceive('callTahsilat')
            ->with('login', Mockery::any(), Mockery::any())
            ->andReturn(['oturumKimligi' => 's', 'guvenlikKodu' => 'g', 'seriNo' => 'K', 'kulNo' => '5']);

        $soap->shouldReceive('callTahsilat')
            ->with('makbuzIptal', Mockery::on(function (array $p) {
                return $p['seriNo'] === 'K'
                    && $p['makbuzNo'] === 824
                    && ! empty($p['iptalTarihi'])
                    && ! empty($p['aciklama'])
                    && ! isset($p['masterMakbuzNo'])
                    && ! isset($p['SeriNo'])
                    && ! isset($p['MakbuzNo']);
            }))
            ->andReturn(['sonucKodu' => 0]);

        $this->app->instance(BelsisSoapClient::class, $soap);

        $this->app->make(BelsisTahsilatService::class)->makbuzIptal(824, 'K', 'test iptal');

        $this->addToAssertionCount(1);
    }

    public function test_tahakkuk_iptal_sends_wsdl_required_fields(): void
    {
        $soap = Mockery::mock(BelsisSoapClient::class);

        $soap->shouldReceive('callTahakkuk')
            ->with('login', Mockery::any(), Mockery::any())
            ->andReturn(['oturumKimligi' => 's', 'guvenlikKodu' => 'g', 'seriNo' => 'K', 'kulNo' => '7']);

        $soap->shouldReceive('callTahakkuk')
            ->with('tahakkukIptal', Mockery::on(function (array $p) {
                return $p['tahID'] === '123456'
                    && $p['kulno'] === 7
                    && ! empty($p['iptalTarihi'])
                    && ! empty($p['iptalAciklama'])
                    && ! isset($p['tahId']);
            }))
            ->andReturn(['sonucKodu' => 0]);

        $this->app->instance(BelsisSoapClient::class, $soap);

        $this->app->make(BelsisTahakkukService::class)->tahakkukIptal('123456');

        $this->addToAssertionCount(1);
    }
}
