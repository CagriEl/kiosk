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

    protected $description = 'Belsis bağlantı sorunlarını teşhis eder (IP, URL, login)';

    public function handle(BelsisAuthService $auth): int
    {
        $this->info('=== Belsis Teşhis ===');
        $this->line('Tahsilat URL : '.config('belsis.tahsilat_url'));
        $this->line('Tahakkuk URL : '.config('belsis.tahakkuk_url'));
        $this->line('Kullanıcı    : '.config('belsis.username'));
        $this->line('SOAP ipAdresi: '.BelsisIpResolver::resolve().' (auto: '.BelsisIpResolver::detect().')');

        $this->newLine();
        $this->info('Dış IP kontrolü...');
        try {
            $publicIp = Http::timeout(5)->get('https://api.ipify.org')->body();
            $this->line('Sunucunun dış IP adresi: '.$publicIp);
            $this->warn('Bu IP adresinin Belsis\'te yetkili olması gerekir.');
        } catch (\Throwable $e) {
            $this->warn('Dış IP tespit edilemedi.');
        }

        $this->newLine();
        $this->info('Login denemesi...');
        try {
            $auth->forgetSession();
            $session = $auth->openSession();
            $this->info('Başarılı! Oturum: '.substr($session['oturumKimligi'], 0, 20).'...');
            $this->line('Seri No: '.($session['seriNo'] ?? '-'));

            return self::SUCCESS;
        } catch (BelsisException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
