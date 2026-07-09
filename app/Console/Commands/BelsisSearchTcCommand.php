<?php

namespace App\Console\Commands;

use App\Exceptions\BelsisException;
use App\Services\Belsis\BelsisAuthService;
use App\Services\Belsis\BelsisBorcSorgulaService;
use App\Services\Belsis\BelsisSoapClient;
use Illuminate\Console\Command;

class BelsisSearchTcCommand extends Command
{
    protected $signature = 'belsis:search-tc {tc : 11 haneli T.C. Kimlik No}';

    protected $description = 'TC kimlik ile sicil arama (tüm sorguTip kombinasyonları) teşhisi';

    public function handle(
        BelsisAuthService $auth,
        BelsisSoapClient $client,
        BelsisBorcSorgulaService $borc,
    ): int {
        $tc = preg_replace('/\D/', '', $this->argument('tc'));

        if (strlen($tc) !== 11) {
            $this->error('11 haneli T.C. Kimlik No giriniz.');

            return self::FAILURE;
        }

        $this->info('Login...');
        try {
            $auth->forgetSession();
            $session = $auth->openSession();
            $this->line('Oturum OK — seri: '.($session['seriNo'] ?? '-'));
        } catch (BelsisException $e) {
            $this->error('Login başarısız: '.$e->getMessage());

            return self::FAILURE;
        }

        $base = $auth->baseParams();
        $tips = array_values(array_unique(array_merge(
            config('belsis.borc_sorgu_tips_tc', []),
            config('belsis.arama_sorgu_tips', []),
        )));

        $this->newLine();
        $this->info('=== arama methodu ===');
        foreach ($tips as $tip) {
            $this->line("sorguTip=<comment>{$tip}</comment>");
            try {
                $result = $client->callTahsilat('arama', array_merge($base, [
                    'sorguTip' => $tip,
                    'sorguNo'  => $tc,
                ]));
                $this->dumpAramaResult($result);
            } catch (BelsisException $e) {
                $this->warn('  Hata: '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->info('=== borcSorgula (gensicilno=0) ===');
        foreach ($tips as $tip) {
            $this->line("sorguTip=<comment>{$tip}</comment>");
            try {
                $result = $client->callTahsilat('borcSorgula', array_merge($base, [
                    'sorguTip'            => $tip,
                    'sorguNo'             => $tc,
                    'gensicilno'          => 0,
                    'indirimliOdenecekMi' => 0,
                    'indirimHakkiVarMi'   => 0,
                ]));
                $this->dumpBorcResult($result);
            } catch (BelsisException $e) {
                $this->warn('  Hata: '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->info('=== birleşik borc sorgusu (otomatik) ===');
        try {
            $result = $borc->query($tc);
            $this->dumpBorcResult($result);
            $this->info('Otomatik sorgu başarılı.');
        } catch (BelsisException $e) {
            $this->error('Otomatik sorgu başarısız: '.$e->getMessage());
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function dumpAramaResult(array $result): void
    {
        $siciller = $result['Siciller']['SicilaramaObj'] ?? $result['Siciller'] ?? null;

        if (! is_array($siciller)) {
            $this->warn('  Sicil listesi boş');

            return;
        }

        $items = isset($siciller[0]) || ! isset($siciller['gensicilno']) ? $siciller : [$siciller];

        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }
            $g = $row['gensicilno'] ?? $row['gensicilNo'] ?? '?';
            $ad = trim(($row['adi'] ?? '').' '.($row['soyadi'] ?? ''));
            $this->line("  gensicilno: <info>{$g}</info> — {$ad}");
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function dumpBorcResult(array $result): void
    {
        $sicil = $result['Sicil'] ?? [];
        $no = is_array($sicil) ? ($sicil['sicilNo'] ?? $sicil['gensicilno'] ?? '-') : '-';
        $name = is_array($sicil) ? ($sicil['adiSoyadiUnvani'] ?? '-') : '-';
        $this->line("  sicilNo: <info>{$no}</info> — {$name}");
    }
}
