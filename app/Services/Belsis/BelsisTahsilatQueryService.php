<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use Illuminate\Support\Arr;

class BelsisTahsilatQueryService
{
    public function __construct(
        private readonly BelsisSoapClient $client,
        private readonly BelsisAuthService $auth,
    ) {}

    /**
     * @return array{identityNo: string, fullName: string, sicilNo: string, adi?: string, soyadi?: string}
     */
    public function getCitizen(string $identityNo): array
    {
        $gensicilno = $this->resolveGensicilNo($identityNo);
        $borc = $this->fetchBorcSorgula((int) $gensicilno);

        $sicil = $borc['Sicil'] ?? [];
        $fullName = trim((string) ($sicil['adiSoyadiUnvani'] ?? ''));

        if ($fullName === '') {
            $fullName = $this->lookupNameFromSicilSorgula((int) $gensicilno) ?? 'Sicil No: '.$gensicilno;
        }

        [$adi, $soyadi] = $this->splitName($fullName);

        return [
            'identityNo' => $identityNo,
            'fullName'   => $fullName,
            'sicilNo'    => (string) ($sicil['sicilNo'] ?? $gensicilno),
            'adi'        => $adi,
            'soyadi'     => $soyadi,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDebts(string $identityNo): array
    {
        $gensicilno = $this->resolveGensicilNo($identityNo);
        $borc = $this->fetchBorcSorgula((int) $gensicilno);

        $debts = [];
        $sicil = $borc['Sicil'] ?? [];
        $moduller = $this->normalizeList($sicil['modulListesi']['Modul'] ?? $sicil['modulListesi'] ?? []);

        foreach ($moduller as $modul) {
            if (! is_array($modul)) {
                continue;
            }

            $donemler = $this->normalizeList($modul['donemListesi']['Donem'] ?? $modul['donemListesi'] ?? []);

            foreach ($donemler as $donem) {
                if (! is_array($donem)) {
                    continue;
                }

                $tahakkuklar = $this->normalizeList($donem['tahakkukListesi']['Tahakkuk'] ?? $donem['tahakkukListesi'] ?? []);

                foreach ($tahakkuklar as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $debt = $this->mapDebt($item, $donem);
                    if ($debt['amount'] > 0) {
                        $debts[] = $debt;
                    }
                }
            }
        }

        return $debts;
    }

    private function resolveGensicilNo(string $identityNo): string
    {
        $identityNo = trim($identityNo);

        if (! ctype_digit($identityNo)) {
            throw new BelsisException('Geçersiz kimlik numarası. Sadece rakam giriniz.');
        }

        if (strlen($identityNo) === 11) {
            return $this->findGensicilByTc($identityNo);
        }

        if (strlen($identityNo) < 5) {
            throw new BelsisException('Geçersiz kimlik numarası. En az 5 haneli olmalıdır.');
        }

        return $identityNo;
    }

    private function findGensicilByTc(string $tcKimlikNo): string
    {
        $tips = ['TC', 'TcKimlikNo', '2', 'TCKIMLIK'];

        foreach ($tips as $tip) {
            try {
                $result = $this->client->callTahsilat('arama', array_merge(
                    $this->auth->baseParams(),
                    ['sorguTip' => $tip, 'sorguNo' => $tcKimlikNo],
                ));

                $siciller = $this->normalizeList($result['Siciller']['SicilaramaObj'] ?? $result['Siciller'] ?? []);

                if (! empty($siciller[0]['gensicilno'])) {
                    return (string) $siciller[0]['gensicilno'];
                }
            } catch (BelsisException) {
                continue;
            }
        }

        throw new BelsisException('T.C. Kimlik No ile sicil bulunamadı.');
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchBorcSorgula(int $gensicilno): array
    {
        return $this->client->callTahsilat('borcSorgula', array_merge(
            $this->auth->baseParams(),
            [
                'gensicilno'          => $gensicilno,
                'indirimliOdenecekMi' => 0,
                'indirimHakkiVarMi'   => 0,
            ],
        ));
    }

    private function lookupNameFromSicilSorgula(int $gensicilno): ?string
    {
        try {
            $result = $this->client->callTahsilat('sicilSorgula', array_merge(
                $this->auth->baseParams(),
                [
                    'gensicilno' => $gensicilno,
                    'koyID'      => 0,
                    'mukellefNo' => (string) $gensicilno,
                ],
            ));

            $siciller = $this->normalizeList($result['sicilListesi']['sicilAlanlari'] ?? $result['sicilListesi'] ?? []);
            $first = $siciller[0] ?? null;

            if (! is_array($first)) {
                return null;
            }

            $ad = trim((string) ($first['adi'] ?? ''));
            $soyad = trim((string) ($first['soyadi'] ?? ''));

            return trim($ad.' '.$soyad) ?: null;
        } catch (BelsisException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $donem
     * @return array<string, mixed>
     */
    private function mapDebt(array $item, array $donem): array
    {
        $tahakkukNo = (string) ($item['tahakkukNo'] ?? '');
        $odenecek = (float) ($item['odenecekTutar'] ?? 0);
        $tahakkukTutari = (float) ($item['tahakkukTutari'] ?? $odenecek);
        $gecikme = (float) ($item['gecikmeZammi'] ?? 0);

        $period = trim(implode(' / ', array_filter([
            isset($donem['borcYili']) ? $donem['borcYili'].' Yılı' : null,
            isset($donem['taksit']) ? 'Taksit '.$donem['taksit'] : null,
        ])));

        return [
            'id'      => $tahakkukNo,
            'type'    => (string) ($item['turu'] ?? $item['aciklama'] ?? $item['beyanBilgisi'] ?? 'Tahakkuk'),
            'period'  => $period,
            'amount'  => $odenecek,
            'dueDate' => $this->normalizeDate($item['sonOdemeTarihi'] ?? null),
            'meta'    => [
                'tahakkukNo'      => $tahakkukNo,
                'tahakkukTutari'  => $tahakkukTutari,
                'gecikmeTutari'   => $gecikme,
                'odemeTutari'     => $odenecek,
                'aciklama'        => $item['aciklama'] ?? null,
                'beyanBilgisi'    => $item['beyanBilgisi'] ?? null,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];

        return [$parts[0] ?? $fullName, $parts[1] ?? ''];
    }

    /**
     * @param  array<int|string, mixed>  $list
     * @return array<int, array<string, mixed>>
     */
    private function normalizeList(mixed $list): array
    {
        if (empty($list)) {
            return [];
        }

        if (is_array($list) && Arr::isAssoc($list) && ! isset($list[0])) {
            return [$list];
        }

        return array_values(array_filter(array_map(
            fn ($item) => is_array($item) ? $item : null,
            is_array($list) ? $list : [],
        )));
    }

    private function normalizeDate(mixed $value): string
    {
        if (empty($value)) {
            return now()->toDateString();
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }
}
