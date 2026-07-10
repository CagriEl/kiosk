<?php

namespace App\Console\Commands;

use App\Exceptions\BelsisException;
use App\Services\Belsis\BelsisAuthService;
use App\Services\Belsis\BelsisTahakkukService;
use App\Services\Belsis\BelsisTahsilatCatalogService;
use App\Services\Belsis\BelsisTahsilatQueryService;
use App\Services\Belsis\BelsisTahsilatService;
use Illuminate\Console\Command;

class BelsisWebServisTestCommand extends Command
{
    protected $signature = 'belsis:webservis-test
                            {sicil? : Test sicil numarası (varsayılan: 89874)}
                            {--skip-payment : odemeYap çağrısını atla}
                            {--pay-debt= : Bu borç ID\'si için GERÇEK odemeYap tahsilatı dener (borcSorgula/tahakkukBilgileriniGetir çıktısındaki id)}
                            {--no-auto-cancel : Test tahsilatını odendikten sonra otomatik makbuzIptal ile geri almayı atla}';

    protected $description = 'webservis/ dokümantasyonundaki tüm tahsilat ve tahakkuk methodlarını sırayla test eder';

    public function handle(
        BelsisAuthService $auth,
        BelsisTahsilatCatalogService $catalog,
        BelsisTahsilatQueryService $query,
        BelsisTahakkukService $tahakkuk,
        BelsisTahsilatService $tahsilat,
    ): int {
        $sicil = (string) ($this->argument('sicil') ?: '89874');

        $this->info('Belsis webservis tam kapsam testi — sicil: '.$sicil);
        $this->newLine();

        $passed = 0;
        $failed = 0;
        $skipped = 0;

        $run = function (string $label, callable $callback, bool $skip = false) use (&$passed, &$failed, &$skipped) {
            if ($skip) {
                $this->line("  <fg=yellow>SKIP</> {$label}");
                $skipped++;

                return;
            }

            try {
                $callback();
                $this->line("  <fg=green>OK</> {$label}");
                $passed++;
            } catch (BelsisException $e) {
                $this->line("  <fg=red>FAIL</> {$label}: ".$e->getMessage());
                $failed++;
            } catch (\Throwable $e) {
                $this->line("  <fg=red>ERR</> {$label}: ".$e->getMessage());
                $failed++;
            }
        };

        $this->comment('Tahsilat servisi');
        $run('login (oturum)', fn () => $auth->openSession());
        $run('odemeSekilleri', fn () => $catalog->getOdemeSekilleri());
        $run('kdvHesaplari', fn () => $catalog->getKdvHesaplari());
        $run('kdvOranlari', fn () => $catalog->getKdvOranlari());
        $run('tahakkukTurleri (tahsilat)', fn () => $catalog->getTahakkukTurleri());
        $run('sicilSorgula', fn () => $query->sicilSorgula((int) $sicil));
        $run('sicilBorcBeyanSorgula', fn () => $query->sicilBorcBeyanSorgula((int) $sicil));

        $borclar = [];
        $run('borcSorgula', function () use ($query, $sicil, &$borclar) {
            $borclar = $query->getDebts($sicil);
        });

        if ($borclar !== []) {
            $this->line('  Borçlar (--pay-debt için id kullanın):');
            foreach ($borclar as $debt) {
                $this->line(sprintf(
                    '    - id=%s  %s  %s TL',
                    $debt['id'],
                    mb_substr((string) $debt['type'], 0, 40),
                    number_format((float) $debt['amount'], 2, ',', '.'),
                ));
            }
        } else {
            $this->line('  <fg=yellow>Borç bulunamadı — --pay-debt için kullanılabilir bir id yok.</>');
        }

        $run('arama (sicil)', fn () => $query->search('SICIL', $sicil));
        $run('mukellefMakbuzSorgula', fn () => $query->mukellefMakbuzSorgula((int) $sicil));
        $run('tahsilatSorgula', fn () => $query->tahsilatSorgula((int) $sicil));

        $tahsilatlar = [];
        try {
            $tahsilatlar = $query->tahsilatSorgula((int) $sicil);
        } catch (BelsisException) {
            // ignore
        }

        $firstTahsilat = $tahsilatlar[0]['tahsilatNo'] ?? $tahsilatlar[0]['tahsilatno'] ?? null;
        $run('tahsilatDetaySorgula', fn () => $query->tahsilatDetaySorgula((int) $firstTahsilat), $firstTahsilat === null);

        $makbuzlar = [];
        try {
            $makbuzlar = $query->mukellefMakbuzSorgula((int) $sicil);
        } catch (BelsisException) {
            // ignore
        }

        $firstMakbuz = $makbuzlar[0] ?? null;
        $run('makbuzSorgula', function () use ($query, $firstMakbuz) {
            $makbuzId = (int) ($firstMakbuz['makbuzID'] ?? $firstMakbuz['masterMakbuzNo'] ?? 0);
            $query->makbuzSorgula(
                $makbuzId,
                isset($firstMakbuz['seriNo']) ? (string) $firstMakbuz['seriNo'] : null,
                isset($firstMakbuz['makbuzNo']) ? (int) $firstMakbuz['makbuzNo'] : null,
            );
        }, $firstMakbuz === null);

        $this->newLine();
        $this->comment('Tahakkuk servisi');
        $run('tahakkukTurleri', fn () => $tahakkuk->getTahakkukTurleri());
        $run('kdvHesaplari (tahakkuk)', fn () => $tahakkuk->getKdvHesaplari());
        $run('kdvOranlari (tahakkuk)', fn () => $tahakkuk->getKdvOranlari());
        $run('tahakkukBilgileriniGetir', fn () => $tahakkuk->getDebts($sicil));
        $run('genmahSorgulaCombo', fn () => $tahakkuk->genmahSorgulaCombo());
        $run('gmkSorgula', fn () => $tahakkuk->gmkSorgula((int) $sicil));
        $run('sicilSorgula (tahakkuk)', fn () => $tahakkuk->getCitizen($sicil));

        $payDebtId = $this->option('pay-debt');

        if ($this->option('skip-payment') || $payDebtId === null) {
            $run('odemeYap (tahakkuklu)', fn () => null, true);
            $run('makbuzIptal', fn () => null, true);
            $run('tahakkukEkle', fn () => null, true);
            $run('tahakkukIptal', fn () => null, true);

            if ($payDebtId === null && ! $this->option('skip-payment')) {
                $this->newLine();
                $this->comment('odemeYap gerçek bir tahsilat testi için: --pay-debt=<borcID> ekleyin (borç listesindeki id).');
            }
        } else {
            $this->newLine();
            $this->comment('Gerçek tahsilat testi (odemeYap)');
            $this->runPaymentTest($tahakkuk, $tahsilat, $sicil, (string) $payDebtId, $borclar);
        }

        $this->newLine();
        $this->info("Sonuç: {$passed} başarılı, {$failed} hatalı, {$skipped} atlandı");

        if ($failed > 0) {
            $this->comment('IP yetkisi veya oturum hatası varsa: php artisan belsis:diagnose');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * --pay-debt ile açıkça istenmediği sürece hiçbir gerçek kayıt oluşturmaz.
     * odemeYap Belsis'te gerçek bir tahsilat/makbuz kaydı yaratır; bu yüzden
     * tutar/borç gösterip onay ister ve varsayılan olarak hemen ardından
     * makbuzIptal ile geri alma teklif eder (--no-auto-cancel ile atlanabilir).
     *
     * @param  array<int, array<string, mixed>>  $borclar
     */
    private function runPaymentTest(
        BelsisTahakkukService $tahakkuk,
        BelsisTahsilatService $tahsilat,
        string $sicil,
        string $debtId,
        array $borclar,
    ): void {
        $debt = collect($borclar)->first(fn (array $d) => (string) $d['id'] === $debtId);

        if ($debt === null) {
            $this->error("Borç ID bulunamadı: {$debtId} (borcSorgula çıktısındaki id'lerden birini kullanın)");

            return;
        }

        $citizen = [];
        try {
            $citizen = $tahakkuk->getCitizen($sicil);
        } catch (BelsisException) {
            // isim boş geçilebilir, odemeYap 'VATANDAS' varsayılanına düşer
        }

        $this->warn(sprintf(
            'GERÇEK TAHSİLAT: %s — %s TL (sicil: %s, borç: %s)',
            $debt['type'],
            number_format((float) $debt['amount'], 2, ',', '.'),
            $sicil,
            $debtId,
        ));
        $this->warn('Bu işlem Belsis üzerinde gerçek bir makbuz/tahsilat kaydı oluşturur.');

        if (! $this->confirm('Devam edilsin mi?', false)) {
            $this->line('Vazgeçildi.');

            return;
        }

        try {
            $result = $tahsilat->confirmBankPayment($sicil, [$debt], 'TEST-'.now()->timestamp, $citizen);
        } catch (BelsisException $e) {
            $this->error('Tahsilat başarısız: '.$e->getMessage());

            return;
        }

        $this->info(sprintf(
            'Tahsilat başarılı — Makbuz: %s-%s (ID: %s), Tutar: %s TL',
            $result['seriNo'], $result['makbuzNo'], $result['makbuzID'],
            number_format((float) $result['total'], 2, ',', '.'),
        ));

        if ($this->option('no-auto-cancel')) {
            $this->warn('Otomatik iptal atlandı (--no-auto-cancel). Bu kayıt Belsis üzerinde gerçek tahsilat olarak kalacak.');

            return;
        }

        if (! $this->confirm('Bu test kaydını şimdi makbuzIptal ile geri alalım mı?', true)) {
            $this->warn('Kayıt iptal edilmedi, Belsis üzerinde gerçek bir tahsilat olarak kalacak.');

            return;
        }

        try {
            $tahsilat->makbuzIptal((int) $result['makbuzNo'], (string) $result['seriNo'], 'belsis:webservis-test otomatik iptal');
            $this->info('Test tahsilatı iptal edildi (makbuzIptal).');
        } catch (BelsisException $e) {
            $this->error('İptal başarısız: '.$e->getMessage().' — Belsis IT ile manuel iptali koordine edin.');
        }
    }
}
