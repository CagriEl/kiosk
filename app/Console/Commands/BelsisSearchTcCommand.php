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
        $aramaTips = config('belsis.arama_sorgu_tips', ['2', 'TC', 'TcKimlikNo', 'TCKIMLIK']);
        $borcTips = config('belsis.borc_sorgu_tips_tc', ['2', 'TC', 'TcKimlikNo', 'TCKIMLIK']);

        $this->newLine();
        $this->info('=== sicilSorgula(mukellefNo=TC) — Kırklareli asıl yol ===');
        try {
            $result = $client->callTahsilat('sicilSorgula', array_merge($base, [
                'gensicilno' => 0,
                'koyID'      => 0,
                'mukellefNo' => $tc,
            ]));
            $list = $result['sicilListesi']['sicilAlanlari'] ?? $result['sicilListesi'] ?? [];
            $rows = [];
            if (is_array($list)) {
                $rows = isset($list[0]) || ! isset($list['gensicilno']) ? $list : [$list];
            }
            $matched = false;
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rowTc = preg_replace('/\D/', '', (string) ($row['tcKimlikNo'] ?? ''));
                $g = $row['gensicilno'] ?? '?';
                $ad = trim(($row['adi'] ?? '').' '.($row['soyadi'] ?? ''));
                $ok = $rowTc === $tc ? 'EŞLEŞTİ' : 'tc='.$rowTc;
                $this->line("  gensicilno: <info>{$g}</info> — {$ad} [{$ok}]");
                if ($rowTc === $tc) {
                    $matched = true;
                }
            }
            if (! $matched) {
                $this->warn('  TC ile birebir eşleşen sicil yok');
            }
        } catch (BelsisException $e) {
            $this->warn('  Hata: '.$e->getMessage());
        }

        $this->newLine();
        $this->info('=== arama methodu (TC → gensicil) ===');
        foreach ($aramaTips as $tip) {
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
        $this->info('=== borcSorgula TC (gensicilno=0) ===');
        foreach ($borcTips as $tip) {
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
        $this->info('=== borcSorgula sicil (arama sonrası gensicil ile) ===');
        try {
            $result = $borc->query($tc, 'tc');
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
