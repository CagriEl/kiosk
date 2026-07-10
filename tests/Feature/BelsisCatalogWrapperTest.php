<?php

namespace Tests\Feature;

use App\Services\Belsis\BelsisSoapClient;
use App\Services\Belsis\BelsisTahsilatCatalogService;
use Mockery;
use Tests\TestCase;

/**
 * odemeSekilleri/kdvHesaplari/kdvOranlari yanıtlarının sarmalayıcı (wrapper) etiket
 * adları koddaki tahminlerle uyuşmuyordu: gerçek WSDL 'odemeSekliListesi' (tekil
 * "Sekli") diyor, kod 'odemeSekilleriListesi' (çoğul) arıyordu — üstelik öğe
 * alanları da 'ID'/'aciklama' yerine 'odemeSekliID'/'odemeSekliAdi' aranıyordu.
 * Sonuç: getOdemeSekilleri() gerçek sunucuya karşı her zaman boş dönüyordu.
 * Bu test, WSDL'e uygun gerçek yanıt şeklinin artık doğru ayrıştırıldığını kanıtlar.
 */
class BelsisCatalogWrapperTest extends TestCase
{
    public function test_odeme_sekilleri_parses_real_wsdl_shaped_response(): void
    {
        $soap = Mockery::mock(BelsisSoapClient::class);

        $soap->shouldReceive('callTahsilat')
            ->with('login', Mockery::any(), Mockery::any())
            ->andReturn(['oturumKimligi' => 's', 'guvenlikKodu' => 'g', 'seriNo' => 'K']);

        $soap->shouldReceive('callTahsilat')
            ->with('odemeSekilleri', Mockery::any())
            ->andReturn([
                'odemeSekliListesi' => [
                    'odemeSekilleri' => [
                        ['ID' => 5, 'kullaniciOdemeSekli' => 1, 'aciklama' => 'Kredi Kartı'],
                        ['ID' => 2, 'kullaniciOdemeSekli' => 1, 'aciklama' => 'Banka'],
                    ],
                ],
            ]);

        $this->app->instance(BelsisSoapClient::class, $soap);

        $methods = $this->app->make(BelsisTahsilatCatalogService::class)->getOdemeSekilleri();

        $this->assertSame([
            ['id' => 5, 'name' => 'Kredi Kartı'],
            ['id' => 2, 'name' => 'Banka'],
        ], $methods);
    }

    public function test_kdv_hesaplari_and_oranlari_parse_singular_wrapper(): void
    {
        $soap = Mockery::mock(BelsisSoapClient::class);

        $soap->shouldReceive('callTahsilat')
            ->with('login', Mockery::any(), Mockery::any())
            ->andReturn(['oturumKimligi' => 's', 'guvenlikKodu' => 'g', 'seriNo' => 'K']);

        $soap->shouldReceive('callTahsilat')
            ->with('kdvHesaplari', Mockery::any())
            ->andReturn(['kdvHesabiListesi' => ['kdvHesaplari' => [['tturu' => 1, 'geladi' => 'Emlak']]]]);

        $soap->shouldReceive('callTahsilat')
            ->with('kdvOranlari', Mockery::any())
            ->andReturn(['kdvOraniListesi' => ['kdvOranlari' => [['kdvoran' => 20.0]]]]);

        $this->app->instance(BelsisSoapClient::class, $soap);

        $catalog = $this->app->make(BelsisTahsilatCatalogService::class);

        $this->assertCount(1, $catalog->getKdvHesaplari());
        $this->assertCount(1, $catalog->getKdvOranlari());
    }
}
