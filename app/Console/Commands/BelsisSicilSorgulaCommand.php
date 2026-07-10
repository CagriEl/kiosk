<?php

namespace App\Console\Commands;

use App\Exceptions\BelsisException;
use App\Services\Belsis\BelsisAuthService;
use App\Services\Belsis\BelsisSoapClient;
use Illuminate\Console\Command;

/**
 * webservis/tahakkuk/wsdl-report.html içindeki sicilSorgula operasyonu (op.d1e385) baz alınarak
 * SADECE bu method ile, gensicilno üzerinden sicil sorgusu yapar. borcSorgula/arama/tahakkuk
 * fallback zinciri devre dışıdır — izole test amaçlıdır.
 */
class BelsisSicilSorgulaCommand extends Command
{
    protected $signature = 'belsis:sicil-sorgula {gensicilno : Gensicilno (tahakkuk sicilSorgula)}';

    protected $description = 'Sadece sicilSorgula (tahakkuk) methodu ile gensicilno üzerinden sicil sorgusu — izole test';

    public function handle(BelsisAuthService $auth, BelsisSoapClient $client): int
    {
        $gensicilnoArg = trim($this->argument('gensicilno'));

        if (! ctype_digit($gensicilnoArg)) {
            $this->error('Geçerli bir gensicilno giriniz (sayısal).');

            return self::FAILURE;
        }

        $gensicilno = (int) $gensicilnoArg;

        $this->info('Tahakkuk oturumu açılıyor...');
        try {
            $auth->forgetSession();
            $session = $auth->openTahakkukSession();
            $this->line('Oturum OK — seri: '.($session['seriNo'] ?? '-'));
        } catch (BelsisException $e) {
            $this->error('Tahakkuk login başarısız: '.$e->getMessage().' ['.($e->sonucKodu ?? '-').']');

            return self::FAILURE;
        }

        $params = array_merge($auth->baseParamsTahakkuk(), [
            'gensicilno' => $gensicilno,
            'koyID'      => 0,
            'mukellefNo' => (string) $gensicilno,
        ]);

        $this->newLine();
        $this->info('=== sicilSorgula (tahakkuk) — gensicilno='.$gensicilno.' ===');

        try {
            $result = $client->callTahakkuk('sicilSorgula', $params);
        } catch (BelsisException $e) {
            $this->error('Hata: '.$e->getMessage().' ['.($e->sonucKodu ?? '-').']');

            return self::FAILURE;
        }

        $rows = $result['sicilListesi']['sicilAlanlari'] ?? $result['sicilListesi'] ?? [];
        $rows = is_array($rows) && isset($rows['gensicilno']) ? [$rows] : (array) $rows;

        if (empty($rows)) {
            $this->warn('Kayıt bulunamadı (sonucKodu: '.($result['sonucKodu'] ?? '-').').');

            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $ad = trim(($row['adi'] ?? '').' '.($row['soyadi'] ?? ''));
            $unvan = trim((string) ($row['unvan'] ?? ''));

            $this->line('gensicilno: <info>'.($row['gensicilno'] ?? '?').'</info>');
            $this->line('uyeNo: '.($row['uyeNo'] ?? '?'));
            $this->line('Ad Soyad: '.($ad ?: '-'));
            if ($unvan !== '') {
                $this->line('Unvan: '.$unvan);
            }
            $this->line('TC Kimlik No: '.($row['tcKimlikNo'] ?? '-'));
            $this->line('Baba Adı: '.($row['babaAdi'] ?? '-'));
            $this->line('Doğum Yeri: '.($row['dogumYeri'] ?? '-'));
            $this->line('Doğum Tarihi: '.($row['dogumTarihi'] ?? '-'));
            $this->newLine();
        }

        $this->info('Ham yanıt: '.json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));

        return self::SUCCESS;
    }
}
