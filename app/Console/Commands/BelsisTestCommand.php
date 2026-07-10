<?php

namespace App\Console\Commands;

use App\Exceptions\BelsisException;
use App\Services\Belsis\BelsisAuthService;
use App\Services\Belsis\BelsisKioskService;
use Illuminate\Console\Command;

class BelsisTestCommand extends Command
{
    protected $signature = 'belsis:test {sicil=89874 : Sorgulanacak sicil veya TC}';

    protected $description = 'Belsis SOAP bağlantısını test eder (login → borcSorgula)';

    public function handle(BelsisAuthService $auth, BelsisKioskService $kiosk): int
    {
        $sicil = $this->argument('sicil');

        try {
            if ($this->shouldUseMock($sicil)) {
                $this->warn('Demo sicil ('.$sicil.') — mock verisi kullanılıyor.');
            } else {
                $this->info('Belsis login (tahsilatWebServis)...');
                $session = $auth->openSession();
                $this->line('Oturum: '.substr($session['oturumKimligi'], 0, 16).'...');
                $this->line('Seri No: '.($session['seriNo'] ?? '-'));
            }

            $citizen = $kiosk->getCitizen($sicil);
            $this->info('Vatandaş: '.$citizen['fullName'].' (Sicil: '.$citizen['sicilNo'].')');

            $debts = $kiosk->getDebts($sicil)['debts'];
            $this->info('Borç sayısı: '.count($debts));

            foreach (array_slice($debts, 0, 8) as $debt) {
                $this->line(sprintf(
                    ' - [%s] %s: %s ₺',
                    $debt['id'],
                    mb_substr($debt['type'], 0, 50),
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

    private function shouldUseMock(string $identityNo): bool
    {
        if (! config('belsis.mock')) {
            return false;
        }

        return in_array(trim($identityNo), config('belsis.mock_aboneler', []), true);
    }
}
