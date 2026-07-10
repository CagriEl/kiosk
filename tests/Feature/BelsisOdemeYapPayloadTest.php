<?php

namespace Tests\Feature;

use App\Services\Belsis\BelsisSoapClient;
use App\Services\Belsis\BelsisTahsilatService;
use Mockery;
use Tests\TestCase;

/**
 * Canlıda gözlemlenen hata: "Sistem Hatası — Object reference not set to an instance
 * of an object" (.NET NullReferenceException) odemeYap sırasında alınıyordu.
 * odemeYapTahakkuklu isteğimiz 'tahakkuksuzTahsilat' alanını tamamen atlıyordu; WSDL'de
 * bu alan opsiyonel olsa da, sunucu tarafı muhtemelen null yerine boş bir dizi bekleyip
 * üzerinde foreach yapıyor. Bu test, odemeYap isteğinin artık her iki listeyi de
 * (biri dolu, biri boş eleman olarak) içerdiğini doğrular — odemeYapTahakkuksuz ile
 * simetrik.
 */
class BelsisOdemeYapPayloadTest extends TestCase
{
    public function test_odeme_yap_always_sends_both_tahsilat_lists(): void
    {
        $soap = Mockery::mock(BelsisSoapClient::class);

        $soap->shouldReceive('callTahsilat')
            ->with('login', Mockery::any(), Mockery::any())
            ->andReturn(['oturumKimligi' => 's', 'guvenlikKodu' => 'g', 'seriNo' => 'K']);

        $soap->shouldReceive('callTahsilat')
            ->with('odemeYap', Mockery::on(function (array $p) {
                return isset($p['tahakkukluTahsilat'])
                    && isset($p['tahakkuksuzTahsilat'])
                    && $p['tahakkukluTahsilat']['__items'] !== []
                    && $p['tahakkuksuzTahsilat']['__items'] === [];
            }))
            ->andReturn(['makbuzID' => 0, 'makbuzNo' => 0, 'seriNo' => '']);

        $this->app->instance(BelsisSoapClient::class, $soap);

        $result = $this->app->make(BelsisTahsilatService::class)->confirmBankPayment(
            '89874',
            [['id' => '123456', 'amount' => 1500.5, 'meta' => ['tahakkukNo' => '123456', 'tahakkukTutari' => 1500.5]]],
            'TXN-1',
            ['adi' => 'Ahmet', 'soyadi' => 'Yilmaz'],
        );

        $this->assertSame('completed', $result['status']);
    }
}
