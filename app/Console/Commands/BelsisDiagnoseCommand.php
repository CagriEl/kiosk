<?php

namespace App\Console\Commands;

use App\Exceptions\BelsisException;
use App\Services\Belsis\BelsisAuthService;
use App\Services\Belsis\BelsisIpResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class BelsisDiagnoseCommand extends Command
{
    protected $signature = 'belsis:diagnose';

    protected $description = 'Belsis bağlantı sorunlarını teşhis eder (DNS, port, IP, login)';

    public function handle(BelsisAuthService $auth): int
    {
        $this->info('=== Belsis Teşhis ===');
        $this->line('Tahsilat URL : '.config('belsis.tahsilat_url'));
        $this->line('Tahakkuk URL : '.config('belsis.tahakkuk_url'));
        $this->line('Host IP     : '.(config('belsis.host_ip') ?: '(DNS kullanılıyor)'));
        $this->line('Kullanıcı    : '.config('belsis.username'));
        $this->line('SOAP ipAdresi: '.BelsisIpResolver::resolve().' (auto: '.BelsisIpResolver::detect().')');
        $this->line('PHP çalıştığı makine: '.gethostname().' / '.php_uname('n'));

        $this->newLine();
        $this->info('1) DNS / TCP kontrolü...');
        $this->probeEndpoint((string) config('belsis.tahsilat_url'), 'Tahsilat');
        $this->probeEndpoint((string) config('belsis.tahakkuk_url'), 'Tahakkuk');

        $this->newLine();
        $this->info('2) Dış IP kontrolü...');
        try {
            $publicIp = Http::timeout(5)->get('https://api.ipify.org')->body();
            $this->line('Bu PHP sürecinin görünen dış IP: '.$publicIp);
            $this->warn('Belsis yetkisi genelde kiosk sunucusunun yerel/VPN IP’sine verilir; dış IP değil.');
        } catch (\Throwable) {
            $this->warn('Dış IP tespit edilemedi (internet çıkışı yok olabilir — iç ağda sorun değil).');
        }

        $this->newLine();
        $this->info('3) Login denemesi...');
        try {
            $auth->forgetSession();
            $session = $auth->openSession();
            $this->info('Başarılı! Oturum: '.substr($session['oturumKimligi'], 0, 20).'...');
            $this->line('Seri No: '.($session['seriNo'] ?? '-'));

            return self::SUCCESS;
        } catch (BelsisException $e) {
            $this->error($e->getMessage());
            $this->newLine();
            $this->warn('Sık görülen durumlar (ping çalışsa bile):');
            $this->line('- Ping ettiğiniz PC ≠ PHP’nin çalıştığı sunucu (DNS farklı olabilir)');
            $this->line('- Ping (ICMP) açık, ama TCP 1685 kapalı / güvenlik duvarı');
            $this->line('- Hostname çözülmüyor: URL hostname kalsın, .env’ye BELSIS_HOST_IP=x.x.x.x ekleyin');
            $this->line('- URL’de düz IP: IIS “Invalid Hostname” — hostname + BELSIS_HOST_IP kullanın');
            $this->line('- HTML / yetkisiz_ip: kiosk sunucu IP’sini Belsis IT’ye yetkilendirin');
            $this->line('- Eski hata cache: php artisan cache:clear');

            return self::FAILURE;
        }
    }

    private function probeEndpoint(string $url, string $label): void
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $port = (int) (parse_url($url, PHP_URL_PORT) ?: (str_starts_with($url, 'https') ? 443 : 80));

        if ($host === '') {
            $this->error("{$label}: geçersiz URL");

            return;
        }

        $forcedIp = trim((string) config('belsis.host_ip', ''));
        $ip = $host;
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->line("{$label} hedef IP: {$host}");
            $this->warn("  → URL’de düz IP kullanmayın (IIS “Invalid Hostname” verir). Hostname + BELSIS_HOST_IP kullanın.");
        } elseif ($forcedIp !== '' && filter_var($forcedIp, FILTER_VALIDATE_IP)) {
            $ip = $forcedIp;
            $this->line("{$label} BELSIS_HOST_IP: {$host} → {$ip} (DNS atlandı)");
        } else {
            $resolved = gethostbyname($host);
            if ($resolved === $host) {
                $this->error("{$label} DNS BAŞARISIZ: {$host} çözülemedi (PHP getaddrinfo).");
                $this->line('  → .env: BELSIS_HOST_IP=10.0.0.98 (URL hostname kalsın)');

                return;
            }
            $ip = $resolved;
            $this->line("{$label} DNS OK: {$host} → {$ip}");
        }

        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($ip, $port, $errno, $errstr, 5);
        if ($fp) {
            fclose($fp);
            $this->info("{$label} TCP OK: {$ip}:{$port} açık");
        } else {
            $this->error("{$label} TCP BAŞARISIZ: {$ip}:{$port} — {$errno} {$errstr}");
            $this->line('  → Ping çalışması yeterli değil; Belsis HTTP portu (1685) PHP sunucusundan açık olmalı.');
        }
    }
}
