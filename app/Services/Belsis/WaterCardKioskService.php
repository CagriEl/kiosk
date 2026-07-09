<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use Illuminate\Support\Str;

class WaterCardKioskService
{
    private const KONTOR_TONAJ = [5, 10, 20, 30, 40, 50];

    private const TON_BIRIM_FIYAT = [
        'baylan' => 48.75,
        'metlab' => 46.50,
    ];

    /**
     * @return array<string, mixed>
     */
    public function readCard(string $vendor, ?string $aboneNo = null): array
    {
        $vendor = $this->normalizeVendor($vendor);
        $aboneNo = $aboneNo ?: $this->defaultMockAbone($vendor);

        return [
            'vendor'      => $vendor,
            'aboneNo'     => $aboneNo,
            'cardPayload' => $this->mockCardPayload($aboneNo),
            'subscriber'  => $this->getSubscriber($vendor, $aboneNo),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscriber(string $vendor, string $aboneNo): array
    {
        $vendor = $this->normalizeVendor($vendor);
        $profile = $this->mockProfile($vendor, $aboneNo);

        if ($profile === null) {
            throw new BelsisException(
                'Abone eşleştirilemedi. Kartınızı su abonelik servisine götürünüz.',
            );
        }

        return $profile;
    }

    /**
     * @return array{invoices: array<int, array<string, mixed>>}
     */
    public function getInvoices(string $vendor, string $aboneNo): array
    {
        $subscriber = $this->getSubscriber($vendor, $aboneNo);

        return ['invoices' => $this->mockInvoices($vendor, $subscriber)];
    }

    /**
     * @return array{tons: int, unitPrice: float, amount: float, breakdown: array<string, float>}
     */
    public function calculateKontor(string $vendor, string $aboneNo, int $tons): array
    {
        $vendor = $this->normalizeVendor($vendor);
        $this->getSubscriber($vendor, $aboneNo);

        if (! in_array($tons, self::KONTOR_TONAJ, true)) {
            throw new BelsisException('Geçersiz kontör miktarı.');
        }

        $unit = self::TON_BIRIM_FIYAT[$vendor];
        $su = round($tons * $unit, 2);
        $atik = round($su * 0.45, 2);
        $kdv = round(($su + $atik) * 0.20, 2);

        return [
            'tons'      => $tons,
            'unitPrice' => $unit,
            'amount'    => round($su + $atik + $kdv, 2),
            'breakdown' => [
                'su'    => $su,
                'atik'  => $atik,
                'kdv'   => $kdv,
            ],
        ];
    }

    /**
     * @param  array<int, string>  $invoiceIds
     * @return array{transactionId: string, total: float, status: string, receiptNo: string}
     */
    public function payInvoices(string $vendor, string $aboneNo, array $invoiceIds): array
    {
        $invoices = collect($this->getInvoices($vendor, $aboneNo)['invoices']);
        $selected = $invoices->whereIn('id', $invoiceIds)->values();

        if ($selected->isEmpty()) {
            throw new BelsisException('Seçilen fatura bulunamadı.');
        }

        return [
            'transactionId' => 'WTR-'.Str::upper(Str::random(10)),
            'total'         => (float) $selected->sum('amount'),
            'status'        => 'pending_pos',
            'receiptNo'     => null,
        ];
    }

    /**
     * @return array{transactionId: string, status: string, receiptNo: string, loadedTons: int}
     */
    public function confirmInvoicePayment(string $vendor, string $aboneNo, string $transactionId, array $invoiceIds): array
    {
        return [
            'transactionId' => $transactionId,
            'status'        => 'completed',
            'receiptNo'     => 'SU-'.random_int(100000, 999999),
            'loadedTons'    => 0,
            'message'       => 'Su faturası ödemesi tamamlandı.',
        ];
    }

    /**
     * @return array{loadedTons: int, beyanNo: string, message: string}
     */
    public function loadAdvance(string $vendor, string $aboneNo): array
    {
        $subscriber = $this->getSubscriber($vendor, $aboneNo);

        if (($subscriber['unpaidAdvance'] ?? 0) > 0) {
            throw new BelsisException(
                'Aboneliğinize ait ödenmemiş avans kredi yüklemesi bulunmaktadır. Yükleme yapılamaz.',
                '-2',
            );
        }

        $tons = (int) ($subscriber['advanceTons'] ?? 3);

        return [
            'loadedTons' => $tons,
            'beyanNo'    => (string) random_int(10000, 99999),
            'message'    => $tons.' kontör yüklenerek işlem tamamlandı. 7 gün içinde avans su tutarını ödemediğiniz takdirde gecikme zammı uygulanır.',
        ];
    }

    /**
     * @return array{transactionId: string, total: float, status: string}
     */
    public function initiateKontorPayment(string $vendor, string $aboneNo, int $tons): array
    {
        $calc = $this->calculateKontor($vendor, $aboneNo, $tons);

        return [
            'transactionId' => 'WTK-'.Str::upper(Str::random(10)),
            'total'         => $calc['amount'],
            'tons'          => $tons,
            'status'        => 'pending_pos',
        ];
    }

    /**
     * @return array{transactionId: string, status: string, receiptNo: string, loadedTons: int, message: string}
     */
    public function confirmKontorPayment(string $vendor, string $aboneNo, string $transactionId, int $tons): array
    {
        $this->calculateKontor($vendor, $aboneNo, $tons);

        return [
            'transactionId' => $transactionId,
            'status'        => 'completed',
            'receiptNo'     => 'SK-'.random_int(100000, 999999),
            'loadedTons'    => $tons,
            'message'       => $tons.' ton kontör kartınıza yüklendi.',
        ];
    }

    /**
     * @return array<int, int>
     */
    public function kontorOptions(): array
    {
        return self::KONTOR_TONAJ;
    }

    private function normalizeVendor(string $vendor): string
    {
        $vendor = strtolower(trim($vendor));

        if (! in_array($vendor, ['baylan', 'metlab'], true)) {
            throw new BelsisException('Geçersiz sayaç markası.');
        }

        return $vendor;
    }

    private function defaultMockAbone(string $vendor): string
    {
        $list = config("belsis.water_mock_aboneler.{$vendor}", []);

        if (empty($list)) {
            throw new BelsisException('Test abone numarası tanımlı değil.');
        }

        return (string) $list[0];
    }

    private function mockCardPayload(string $aboneNo): string
    {
        $padded = str_pad($aboneNo, 10, '0', STR_PAD_LEFT);

        return str_pad('12500', 10, '0', STR_PAD_LEFT)
            .str_pad('2500', 10, '0', STR_PAD_LEFT)
            .str_pad('0', 10, '0', STR_PAD_LEFT)
            .str_pad('884210', 10, '0', STR_PAD_LEFT)
            .$padded
            .str_repeat('0', 177);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mockProfile(string $vendor, string $aboneNo): ?array
    {
        $catalog = [
            'baylan' => [
                '12345' => [
                    'fullName'       => 'Ayşe DEMİR',
                    'gensicilno'     => '89874',
                    'sayacNo'        => 'BY-88421',
                    'kartTipi'       => 'Baylan NFC',
                    'tipAciklama'    => 'Mesken',
                    'anaKredi'       => 12.5,
                    'yedekKredi'     => 2.0,
                    'unpaidAdvance'  => 0,
                    'advanceTons'    => 3,
                ],
                '27126' => [
                    'fullName'       => 'Mehmet KAYA',
                    'gensicilno'     => '45210',
                    'sayacNo'        => 'BY-27126',
                    'kartTipi'       => 'Baylan NFC',
                    'tipAciklama'    => 'Ticarethane',
                    'anaKredi'       => 5.0,
                    'yedekKredi'     => 0,
                    'unpaidAdvance'  => 125.40,
                    'advanceTons'    => 3,
                ],
            ],
            'metlab' => [
                '67890' => [
                    'fullName'       => 'Fatma YILDIRIM',
                    'gensicilno'     => '33102',
                    'sayacNo'        => 'ML-67890',
                    'kartTipi'       => 'Metlab IC',
                    'tipAciklama'    => 'Mesken',
                    'anaKredi'       => 8.0,
                    'yedekKredi'     => 1.5,
                    'unpaidAdvance'  => 0,
                    'advanceTons'    => 3,
                ],
                '54321' => [
                    'fullName'       => 'Ali ÇELİK',
                    'gensicilno'     => '77881',
                    'sayacNo'        => 'ML-54321',
                    'kartTipi'       => 'Metlab IC',
                    'tipAciklama'    => 'Sanayi',
                    'anaKredi'       => 20.0,
                    'yedekKredi'     => 5.0,
                    'unpaidAdvance'  => 0,
                    'advanceTons'    => 5,
                ],
            ],
        ];

        $data = $catalog[$vendor][$aboneNo] ?? null;

        if ($data === null) {
            $allowed = config("belsis.water_mock_aboneler.{$vendor}", []);

            if (! in_array($aboneNo, $allowed, true)) {
                return null;
            }

            $data = [
                'fullName'      => 'Test ABONE',
                'gensicilno'    => '10001',
                'sayacNo'       => strtoupper(substr($vendor, 0, 2)).'-'.$aboneNo,
                'kartTipi'      => $vendor === 'baylan' ? 'Baylan NFC' : 'Metlab IC',
                'tipAciklama'   => 'Mesken',
                'anaKredi'      => 10.0,
                'yedekKredi'    => 0,
                'unpaidAdvance' => 0,
                'advanceTons'   => 3,
            ];
        }

        return array_merge($data, [
            'vendor'       => $vendor,
            'aboneNo'      => $aboneNo,
            'sonDonem'     => '2024/06',
            'ilkEndeks'    => 1250,
            'okumaSayisi'  => 3,
            'aboneTipi'    => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $subscriber
     * @return array<int, array<string, mixed>>
     */
    private function mockInvoices(string $vendor, array $subscriber): array
    {
        if (($subscriber['unpaidAdvance'] ?? 0) > 0) {
            return [
                [
                    'id'      => 'AV-'.$subscriber['aboneNo'],
                    'type'    => 'Ödenmemiş Avans Su Borcu',
                    'period'  => $subscriber['sonDonem'],
                    'amount'  => (float) $subscriber['unpaidAdvance'],
                    'dueDate' => now()->subDays(3)->toDateString(),
                ],
            ];
        }

        return [
            [
                'id'      => 'SU-'.$subscriber['aboneNo'].'-05',
                'type'    => 'Su Faturası',
                'period'  => '2024 Mayıs',
                'amount'  => 185.50,
                'dueDate' => '2024-06-15',
            ],
            [
                'id'      => 'SU-'.$subscriber['aboneNo'].'-04',
                'type'    => 'Atık Su Faturası',
                'period'  => '2024 Nisan',
                'amount'  => 92.30,
                'dueDate' => '2024-05-15',
            ],
        ];
    }
}
