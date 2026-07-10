<?php

namespace Tests\Feature;

use App\Exceptions\BelsisException;
use App\Services\Belsis\BelsisSoapClient;
use App\Services\Belsis\BelsisTahsilatQueryService;
use Mockery;
use Tests\TestCase;

/**
 * borcSorgula, tahsilatWebServis_1.wsdl'in asıl "borç sorgulama" methodudur
 * (Sicil > modulListesi > Modul > donemListesi > Donem > tahakkukListesi > Tahakkuk
 * şeklinde iç içe döner). Daha önce hiçbir servis bu methodu çağırmıyordu — getDebts()
 * doğrudan sicilBorcBeyanSorgula'ya (farklı/dar bir veri kaynağı) düşüyordu. Bu test,
 * borcSorgula entegrasyonunun WSDL şemasına göre doğru borç listesi ürettiğini ve
 * yanlış sicile ait sonuçları reddettiğini doğrular.
 */
class BelsisBorcSorgulaIntegrationTest extends TestCase
{
    private const GENSICIL = 89874;

    public function test_borc_sorgula_response_is_mapped_to_debts(): void
    {
        $soap = $this->mockSoapWithLogin();

        $soap->shouldReceive('callTahsilat')
            ->with('borcSorgula', Mockery::on(fn (array $p) => $p['sorguTip'] === 'SICIL'))
            ->andReturn($this->borcSorgulaResponse());

        $this->app->instance(BelsisSoapClient::class, $soap);

        $debts = $this->app->make(BelsisTahsilatQueryService::class)->getDebts((string) self::GENSICIL);

        $this->assertCount(1, $debts);
        $this->assertSame('123456', $debts[0]['id']);
        $this->assertSame(1500.5, $debts[0]['amount']);
        $this->assertSame('Emlak Vergisi', $debts[0]['type']);
        $this->assertSame('borcSorgula', $debts[0]['meta']['kaynak']);
    }

    public function test_borc_sorgula_tries_next_sorgu_tip_on_business_error(): void
    {
        $soap = $this->mockSoapWithLogin();

        $soap->shouldReceive('callTahsilat')
            ->with('borcSorgula', Mockery::on(fn (array $p) => $p['sorguTip'] === 'SICIL'))
            ->andThrow(new BelsisException('Geçersiz sorgu tipi.', '9999'));

        $soap->shouldReceive('callTahsilat')
            ->with('borcSorgula', Mockery::on(fn (array $p) => $p['sorguTip'] !== 'SICIL'))
            ->andReturn($this->borcSorgulaResponse());

        $this->app->instance(BelsisSoapClient::class, $soap);

        $debts = $this->app->make(BelsisTahsilatQueryService::class)->getDebts((string) self::GENSICIL);

        $this->assertCount(1, $debts);
        $this->assertSame('123456', $debts[0]['id']);
    }

    public function test_borc_sorgula_result_for_different_sicil_is_rejected(): void
    {
        $soap = $this->mockSoapWithLogin();

        // SP yanlış sicile ait bir kayıt döndürse bile (ör. sorguTip yanlış yorumlandı),
        // Sicil.sicilNo istenen gensicilno ile eşleşmiyorsa asla kabul edilmemeli.
        $mismatched = $this->borcSorgulaResponse();
        $mismatched['Sicil']['sicilNo'] = 999999;

        $soap->shouldReceive('callTahsilat')
            ->with('borcSorgula', Mockery::any())
            ->andReturn($mismatched);

        $soap->shouldReceive('callTahsilat')
            ->with('sicilBorcBeyanSorgula', Mockery::any())
            ->andReturn(['sicilBorcBeyanListesi' => []]);

        $soap->shouldReceive('callTahakkuk')
            ->with('tahakkukBilgileriniGetir', Mockery::any())
            ->andReturn(['tahakkukListesi' => []]);

        $this->app->instance(BelsisSoapClient::class, $soap);

        $debts = $this->app->make(BelsisTahsilatQueryService::class)->getDebts((string) self::GENSICIL);

        $this->assertSame([], $debts);
    }

    private function mockSoapWithLogin(): \Mockery\MockInterface
    {
        $soap = Mockery::mock(BelsisSoapClient::class);

        $soap->shouldReceive('callTahsilat')
            ->with('login', Mockery::any(), Mockery::any())
            ->andReturn(['oturumKimligi' => 'session-1', 'guvenlikKodu' => 'g', 'seriNo' => 'K']);

        $soap->shouldReceive('callTahakkuk')
            ->with('login', Mockery::any(), Mockery::any())
            ->andReturn(['oturumKimligi' => 'session-2', 'guvenlikKodu' => 'g', 'seriNo' => 'K']);

        return $soap;
    }

    /**
     * @return array<string, mixed>
     */
    private function borcSorgulaResponse(): array
    {
        return [
            'Sicil' => [
                'sicilNo'         => self::GENSICIL,
                'adiSoyadiUnvani' => 'Ahmet YILMAZ',
                'modulListesi'    => [
                    'Modul' => [
                        'modulBilgisi' => 'Emlak Vergisi Modülü',
                        'modulNo'      => 1,
                        'donemListesi' => [
                            'Donem' => [
                                'borcYili'        => 2026,
                                'taksit'          => 1,
                                'tahakkukListesi' => [
                                    'Tahakkuk' => [
                                        'tahakkukNo'     => 123456,
                                        'beyanID'        => 0,
                                        'gensicilNo'     => self::GENSICIL,
                                        'turu'           => 'Emlak Vergisi',
                                        'aciklama'       => 'Emlak Vergisi 1. Taksit',
                                        'tahakkukTutari' => 1500.50,
                                        'indirimTutari'  => 0,
                                        'gecikmeZammi'   => 0,
                                        'odenecekTutar'  => 1500.50,
                                        'odenenTutar'    => 0,
                                        'sonOdemeTarihi' => '2026-05-31T00:00:00',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
