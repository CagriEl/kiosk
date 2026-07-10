<?php

namespace App\Console\Commands;

use App\Exceptions\BelsisException;
use App\Services\Belsis\BelsisKioskService;
use App\Services\Belsis\BelsisTahsilatService;
use Illuminate\Console\Command;

/**
 * Kiosk sayfasında bir kullanıcının yaşadığı TÜM süreci — sicil sorgula → borç
 * listesini gör → bir borç seç → banka ile öde → onayla → makbuz gör — tek
 * komutta, gerçek kiosk kodunu (BelsisKioskService, API'nin kullandığı sınıfın
 * kendisi) çağırarak baştan sona simüle eder.
 */
class BelsisKioskFlowTestCommand extends Command
{
    protected $signature = 'belsis:kiosk-flow
                            {sicil : Sorgulanacak sicil numarası}
                            {--pay-debt= : Bu borç id\'siyle ödemeyi baştan sona dener (GERÇEK odemeYap çağrısı)}';

    protected $description = 'Kiosk sayfasındaki tüm süreci (sicil sorgula → borç seç → öde → onayla → makbuz) tek komutta simüle eder';

    public function handle(BelsisKioskService $kiosk, BelsisTahsilatService $tahsilat): int
    {
        $sicil = (string) $this->argument('sicil');
        $payDebtId = $this->option('pay-debt');

        $this->info('=== 1) Sicil sorgula (Sayfa: kimlik girişi) ===');
        try {
            $citizen = $kiosk->getCitizen($sicil);
        } catch (BelsisException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->line('Vatandaş: '.$citizen['fullName'].' (Sicil: '.$citizen['sicilNo'].')');

        $this->newLine();
        $this->info('=== 2) Borç listesi (Sayfa: borç ekranı) ===');
        try {
            $debts = $kiosk->getDebts($sicil)['debts'];
        } catch (BelsisException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($debts === []) {
            $this->warn('Borç bulunamadı — süreç burada doğal olarak sona erer (ödenecek borç yok).');

            return self::SUCCESS;
        }

        foreach ($debts as $debt) {
            $this->line(sprintf(
                '  - id=%s  %s  %s TL  (son ödeme: %s)',
                $debt['id'],
                mb_substr((string) $debt['type'], 0, 40),
                number_format((float) $debt['amount'], 2, ',', '.'),
                $debt['dueDate'] ?? '-',
            ));
        }

        if ($payDebtId === null) {
            $this->newLine();
            $this->comment("Ödeme adımını da denemek için: --pay-debt=<yukarıdaki id'lerden biri>");

            return self::SUCCESS;
        }

        $debt = collect($debts)->first(fn (array $d) => (string) $d['id'] === (string) $payDebtId);
        if ($debt === null) {
            $this->error("Borç id bulunamadı: {$payDebtId}");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('=== 3) Banka ile öde (Sayfa: "BANKA İLE ÖDE" butonu) ===');
        $this->warn(sprintf(
            'GERÇEK TAHSİLAT: %s — %s TL',
            $debt['type'], number_format((float) $debt['amount'], 2, ',', '.'),
        ));
        if (! $this->confirm('Devam edilsin mi? (initiatePayment çağrılacak)', false)) {
            $this->line('Vazgeçildi.');

            return self::SUCCESS;
        }

        try {
            $payment = $kiosk->initiatePayment($sicil, [(string) $debt['id']]);
        } catch (BelsisException $e) {
            $this->error('Ödeme başlatılamadı: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->line('İşlem no: '.$payment['transactionId'].' — Tutar: '.$payment['total'].' TL — Durum: '.$payment['status']);

        $this->newLine();
        $this->info('=== 4) Ödemeyi onayla (Sayfa: "ÖDEMEYİ ONAYLA" butonu) ===');
        if (! $this->confirm('Onaylansın mı? (confirmPayment → gerçek odemeYap çağrılacak)', false)) {
            $this->line('Vazgeçildi — işlem "pending" durumunda kalacak, Belsis tarafında kayıt oluşmadı.');

            return self::SUCCESS;
        }

        try {
            $confirmation = $kiosk->confirmPayment($sicil, [(string) $debt['id']], $payment['transactionId']);
        } catch (BelsisException $e) {
            $this->error('Onay başarısız: '.$e->getMessage());

            return self::FAILURE;
        }

        if (($confirmation['status'] ?? null) !== 'completed') {
            $this->error('Beklenmeyen durum: '.json_encode($confirmation, JSON_UNESCAPED_UNICODE));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('=== 5) Makbuz (Sayfa: başarı ekranı) ===');
        $receipt = $confirmation['receipt'] ?? [];
        $this->line('Makbuz: '.($confirmation['receiptNo'] ?? '-'));
        $this->line('Tutar: '.($receipt['toplamTutarYazi'] ?? (($receipt['toplamTutar'] ?? $payment['total']).' TL')));

        if ($this->confirm('Bu test kaydını şimdi makbuzIptal ile geri alalım mı?', true)) {
            try {
                $tahsilat->makbuzIptal(
                    (int) $confirmation['makbuzNo'],
                    (string) $confirmation['seriNo'],
                    'belsis:kiosk-flow otomatik iptal',
                );
                $this->info('Test tahsilatı iptal edildi (makbuzIptal).');
            } catch (BelsisException $e) {
                $this->error('İptal başarısız: '.$e->getMessage().' — Belsis IT ile manuel iptali koordine edin.');
            }
        } else {
            $this->warn('Kayıt iptal edilmedi, Belsis üzerinde gerçek bir tahsilat olarak kalacak.');
        }

        return self::SUCCESS;
    }
}
