<?php

namespace App\Console\Commands;

use App\Exceptions\BelsisException;
use App\Services\Belsis\BelsisAuthService;
use App\Services\Belsis\BelsisKioskService;
use Illuminate\Console\Command;

class BelsisTestCommand extends Command
{
    protected $signature = 'belsis:test {sicil=89874 : Sorgulanacak gensicilno}';

    protected $description = 'Belsis SOAP bağlantısını ve borç sorgusunu test eder';

    public function handle(BelsisAuthService $auth, BelsisKioskService $kiosk): int
    {
        $sicil = $this->argument('sicil');

        try {
            if (config('belsis.mock')) {
                $this->warn('BELSIS_MOCK=true — demo verisi kullanılıyor.');
            } else {
                $this->info('Belsis oturum açılıyor...');
                $session = $auth->openSession();
                $this->line('Oturum: '.substr($session['oturumKimligi'], 0, 12).'...');
            }

            $citizen = $kiosk->getCitizen($sicil);
            $this->info('Vatandaş: '.$citizen['fullName'].' (Sicil: '.$citizen['sicilNo'].')');

            $debts = $kiosk->getDebts($sicil)['debts'];
            $this->info('Borç sayısı: '.count($debts));

            foreach (array_slice($debts, 0, 5) as $debt) {
                $this->line(sprintf(
                    ' - [%s] %s: %s ₺',
                    $debt['id'],
                    $debt['type'],
                    number_format($debt['amount'], 2, ',', '.'),
                ));
            }

            return self::SUCCESS;
        } catch (BelsisException $e) {
            $this->error($e->getMessage());
            if ($e->sonucKodu) {
                $this->line('Sonuç kodu: '.$e->sonucKodu);
            }

            return self::FAILURE;
        }
    }
}
