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

        if (! ctype_digit($sicil) || strlen($sicil) < 1) {
            $this->error('Geçerli bir sicil numarası giriniz.');

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
        $aramaTips = config('belsis.arama_sorgu_tips_sicil', ['SICIL', 'GENSICIL', 'UYE', '1', '2', '0']);
        $sicilInt = (int) $sicil;

        $this->newLine();
        $this->info('=== sicilSorgula (tahsilat) — parametre varyantları ===');
        foreach ([
            ['gensicilno' => $sicilInt, 'koyID' => 0, 'mukellefNo' => $sicil],
            ['gensicilno' => $sicilInt, 'koyID' => 0],
            ['gensicilno' => 0, 'koyID' => 0, 'mukellefNo' => $sicil],
        ] as $params) {
            $label = json_encode($params, JSON_UNESCAPED_UNICODE);
            $this->line("  <comment>{$label}</comment>");
            try {
                $result = $client->callTahsilat('sicilSorgula', array_merge($base, $params));
                $rows = $result['sicilListesi']['sicilAlanlari'] ?? $result['sicilListesi'] ?? [];
                $rows = is_array($rows) && isset($rows['gensicilno']) ? [$rows] : (array) $rows;
                if (empty($rows)) {
                    $this->warn('    kayıt yok');
                    continue;
                }
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $ad = trim(($row['adi'] ?? '').' '.($row['soyadi'] ?? ''));
                    $this->line('    gensicilno: <info>'.($row['gensicilno'] ?? '?').'</info>'
                        .' uyeNo: '.($row['uyeNo'] ?? '?').' — '.$ad);
                }
            } catch (BelsisException $e) {
                $this->warn('    Hata: '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->info('=== arama (sicil tipleri) ===');
        foreach ($aramaTips as $tip) {
            $this->line("sorguTip=<comment>{$tip}</comment>");
            try {
                $result = $client->callTahsilat('arama', array_merge($base, [
                    'sorguTip' => $tip,
                    'sorguNo'  => $sicil,
                ]));
                $siciller = $result['Siciller'] ?? $result['siciller'] ?? [];
                $items = $siciller['SicilaramaObj'] ?? $siciller['sicilaramaObj'] ?? $siciller;
                $items = is_array($items) && isset($items['gensicilno']) ? [$items] : (array) $items;
                if (empty($items)) {
                    $this->warn('  kayıt yok');
                    continue;
                }
                foreach ($items as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $ad = trim(($row['adi'] ?? '').' '.($row['soyadi'] ?? ''));
                    $this->line('  <info>OK</info> gensicilno: '.($row['gensicilno'] ?? '?').' — '.$ad);
                }
            } catch (BelsisException $e) {
                $this->warn('  Hata: '.$e->getMessage());
            }
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
