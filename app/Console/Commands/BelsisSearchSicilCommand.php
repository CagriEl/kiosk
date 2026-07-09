<?php

namespace App\Console\Commands;

use App\Exceptions\BelsisException;
use App\Services\Belsis\BelsisAuthService;
use App\Services\Belsis\BelsisBorcSorgulaService;
use App\Services\Belsis\BelsisSoapClient;
use Illuminate\Console\Command;

class BelsisSearchSicilCommand extends Command
{
    protected $signature = 'belsis:search-sicil {sicil : Sicil numarası}';

    protected $description = 'Sicil ile borç sorgusu (tüm sorguTip kombinasyonları) teşhisi';

    public function handle(
        BelsisAuthService $auth,
        BelsisSoapClient $client,
        BelsisBorcSorgulaService $borc,
    ): int {
        $sicil = trim($this->argument('sicil'));

        if (! ctype_digit($sicil) || strlen($sicil) < 5) {
            $this->error('Geçerli bir sicil numarası giriniz (en az 5 hane).');

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
        $tips = config('belsis.borc_sorgu_tips_sicil', ['SICIL', 'GENSICIL', '1', '2', '0']);
        $sicilInt = (int) $sicil;

        $this->newLine();
        $this->info('=== sicilSorgula ===');
        try {
            $result = $client->callTahsilat('sicilSorgula', array_merge($base, [
                'gensicilno' => $sicilInt,
                'koyID'      => 0,
                'mukellefNo' => $sicil,
            ]));
            $rows = $result['sicilListesi']['sicilAlanlari'] ?? $result['sicilListesi'] ?? [];
            $rows = is_array($rows) && isset($rows['gensicilno']) ? [$rows] : (array) $rows;
            if (empty($rows)) {
                $this->warn('  sicilSorgula: kayıt yok');
            }
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $ad = trim(($row['adi'] ?? '').' '.($row['soyadi'] ?? ''));
                $this->line('  gensicilno: <info>'.($row['gensicilno'] ?? '?').'</info> — '.$ad);
            }
        } catch (BelsisException $e) {
            $this->warn('  Hata: '.$e->getMessage());
        }

        $this->newLine();
        $this->info('=== borcSorgula kombinasyonları ===');
        foreach ($tips as $tip) {
            foreach ([$sicilInt, 0] as $gensicilno) {
                $this->line("sorguTip=<comment>{$tip}</comment> gensicilno=<comment>{$gensicilno}</comment>");
                try {
                    $result = $client->callTahsilat('borcSorgula', array_merge($base, [
                        'sorguTip'            => $tip,
                        'sorguNo'             => $sicil,
                        'gensicilno'          => $gensicilno,
                        'indirimliOdenecekMi' => 0,
                        'indirimHakkiVarMi'   => 0,
                    ]));
                    $s = $result['Sicil'] ?? [];
                    $no = is_array($s) ? ($s['sicilNo'] ?? $s['gensicilno'] ?? '-') : '-';
                    $name = is_array($s) ? ($s['adiSoyadiUnvani'] ?? '-') : '-';
                    $this->line("  <info>OK</info> sicilNo: {$no} — {$name}");
                } catch (BelsisException $e) {
                    $this->warn('  Hata: '.$e->getMessage());
                }
            }
        }

        $this->newLine();
        $this->info('=== birleşik borc sorgusu (otomatik) ===');
        try {
            $result = $borc->query($sicil);
            $s = $result['Sicil'] ?? [];
            $no = is_array($s) ? ($s['sicilNo'] ?? $s['gensicilno'] ?? '-') : '-';
            $name = is_array($s) ? ($s['adiSoyadiUnvani'] ?? '-') : '-';
            $this->line("  sicilNo: <info>{$no}</info> — {$name}");
            $this->info('Otomatik sorgu başarılı.');
        } catch (BelsisException $e) {
            $this->error('Otomatik sorgu başarısız: '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
