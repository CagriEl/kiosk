<?php

namespace App\Console\Commands;

use App\Exceptions\BelsisException;
use App\Services\Belsis\BelsisAuthService;
use App\Services\Belsis\BelsisTahakkukService;
use App\Services\Belsis\BelsisTahsilatCatalogService;
use App\Services\Belsis\BelsisTahsilatQueryService;
use Illuminate\Console\Command;

class BelsisWebServisTestCommand extends Command
{
    protected $signature = 'belsis:webservis-test
                            {sicil? : Test sicil numarası (varsayılan: 89874)}
                            {--skip-payment : odemeYap çağrısını atla}';

    protected $description = 'webservis/ dokümantasyonundaki tüm tahsilat ve tahakkuk methodlarını sırayla test eder';

    public function handle(
        BelsisAuthService $auth,
        BelsisTahsilatCatalogService $catalog,
        BelsisTahsilatQueryService $query,
        BelsisTahakkukService $tahakkuk,
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
        $run('borcSorgula', fn () => $query->getDebts($sicil));
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

        if ($this->option('skip-payment')) {
            $run('odemeYap', fn () => null, true);
            $run('makbuzIptal', fn () => null, true);
            $run('tahakkukEkle', fn () => null, true);
            $run('tahakkukIptal', fn () => null, true);
        } else {
            $this->newLine();
            $this->warn('odemeYap / tahakkukEkle / makbuzIptal gerçek kayıt oluşturur — varsayılan olarak atlanır.');
            $run('odemeYap (tahakkuklu)', fn () => null, true);
            $run('odemeYap (tahakkuksuz)', fn () => null, true);
            $run('makbuzIptal', fn () => null, true);
            $run('tahakkukEkle', fn () => null, true);
            $run('tahakkukIptal', fn () => null, true);
        }

        $this->newLine();
        $this->info("Sonuç: {$passed} başarılı, {$failed} hatalı, {$skipped} atlandı");

        if ($failed > 0) {
            $this->comment('IP yetkisi veya oturum hatası varsa: php artisan belsis:diagnose');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
