<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;
use Illuminate\Support\Arr;

class BelsisTahakkukService
{
    public function __construct(
        private readonly BelsisSoapClient $client,
        private readonly BelsisAuthService $auth,
    ) {}

    /**
     * @return array{identityNo: string, fullName: string, sicilNo: string}
     */
    public function getCitizen(string $identityNo): array
    {
        $gensicilno = $this->resolveGensicilNo($identityNo);

        try {
            $result = $this->client->callTahakkuk('gensicilBilgileriniGetir', array_merge(
                $this->auth->baseParams(),
                ['gensicilno' => $gensicilno],
            ));

            return [
                'identityNo' => $identityNo,
                'fullName'   => $this->formatCitizenName($result) ?: 'Vatandaş #'.$gensicilno,
                'sicilNo'    => (string) ($result['gensicilno'] ?? $gensicilno),
            ];
        } catch (BelsisException) {
            return [
                'identityNo' => $identityNo,
                'fullName'   => 'Sicil No: '.$gensicilno,
                'sicilNo'    => $gensicilno,
            ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDebts(string $identityNo): array
    {
        $gensicilno = $this->resolveGensicilNo($identityNo);

        $result = $this->fetchTahakkukList($gensicilno);
        $items = $this->extractTahakkukItems($result);

        return array_values(array_filter(
            array_map(fn (array $item) => $this->mapDebt($item), $items),
            fn (array $debt) => $debt['id'] !== '' && $debt['amount'] > 0,
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPaymentHistory(string $recId): array
    {
        $result = $this->client->callTahakkuk('tahakkukOdemeBilgileriniGetir', array_merge(
            $this->auth->baseParams(),
            ['recId' => $recId],
        ));

        $list = $result['tahakkukListesi']['TahakkukOdemeList'] ?? $result['tahakkukListesi'] ?? [];

        return $this->normalizeList($list);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTahakkukTurleri(): array
    {
        $result = $this->client->callTahakkuk('tahakkukTurleri', $this->auth->baseParams());
        $list = $result['tahakkukTurListesi']['tahakkukTurleri'] ?? [];

        return $this->normalizeList($list);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchTahakkukList(string $gensicilno): array
    {
        $params = array_merge($this->auth->baseParams(), ['gensicilno' => $gensicilno]);

        try {
            return $this->client->callTahakkuk('odenmemisTahakkuklariGetir', $params);
        } catch (BelsisException) {
            return $this->client->callTahakkuk('tahakkukBilgileriniGetir', $params);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<int, array<string, mixed>>
     */
    private function extractTahakkukItems(array $result): array
    {
        $liste = $result['tahakkukListesi'] ?? [];

        if (isset($liste['TahakkukList'])) {
            return $this->normalizeList($liste['TahakkukList']);
        }

        return $this->normalizeList($liste);
    }

    private function resolveGensicilNo(string $identityNo): string
    {
        $identityNo = trim($identityNo);

        if (! ctype_digit($identityNo)) {
            throw new BelsisException('Geçersiz kimlik numarası. Sadece rakam giriniz.');
        }

        if (strlen($identityNo) === 11) {
            try {
                $result = $this->client->callTahakkuk('tcKimlikNoIleGensicilBul', array_merge(
                    $this->auth->baseParams(),
                    ['tcKimlikNo' => $identityNo],
                ));

                $gensicil = $result['gensicilno'] ?? $result['gensicilNo'] ?? null;
                if ($gensicil) {
                    return (string) $gensicil;
                }
            } catch (BelsisException) {
                // Bazı kurulumlarda TC doğrudan gensicilno olarak kabul edilir.
            }
        }

        if (strlen($identityNo) < 5) {
            throw new BelsisException('Geçersiz kimlik numarası. En az 5 haneli olmalıdır.');
        }

        return $identityNo;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapDebt(array $item): array
    {
        $tahId = (string) ($item['tahID'] ?? $item['tahId'] ?? $item['tahakkukId'] ?? $item['recId'] ?? '');
        $amount = (float) ($item['tutar'] ?? $item['borcTutari'] ?? 0);
        $dueDate = $this->normalizeDate($item['sonOdemeTarihi'] ?? $item['tahakkukTarihi'] ?? null);

        $borcYili = $item['borcYili'] ?? '';
        $borcAyi  = $item['borcAyi'] ?? '';
        $taksit   = $item['taksit'] ?? '';

        $period = trim(implode(' / ', array_filter([
            $borcYili ? $borcYili.' Yılı' : null,
            $borcAyi ? 'Ay '.$borcAyi : null,
            $taksit ? 'Taksit '.$taksit : null,
        ]))) ?: ($item['aciklama'] ?? '');

        return [
            'id'      => $tahId,
            'type'    => $item['tahakkukAdi'] ?? $item['aciklama'] ?? 'Tahakkuk',
            'period'  => $period,
            'amount'  => $amount,
            'dueDate' => $dueDate,
            'meta'    => [
                'tahakkukTuru' => $item['tahakkukTuru'] ?? null,
                'aciklama'     => $item['aciklama'] ?? null,
                'borcYili'     => $borcYili,
                'borcAyi'      => $borcAyi,
                'taksit'       => $taksit,
            ],
        ];
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

        if (Arr::isAssoc($list) && $this->looksLikeRecord($list)) {
            return [$list];
        }

        return array_values(array_filter(array_map(function ($item) {
            return is_array($item) ? $item : null;
        }, $list)));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function looksLikeRecord(array $row): bool
    {
        return isset($row['tahID']) || isset($row['tahId']) || isset($row['tutar']) || isset($row['tturu']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     */
    /**
     * @param  array<string, mixed>  $data
     */
    private function formatCitizenName(array $data): ?string
    {
        $direct = $this->pickString($data, ['adSoyad', 'adiSoyadi', 'adSoyadUnvan', 'unvan']);
        if ($direct) {
            return $direct;
        }

        $ad = $this->pickString($data, ['ad', 'adi', 'isim']);
        $soyad = $this->pickString($data, ['soyad', 'soyadi', 'soyisim']);

        if ($ad && $soyad) {
            return trim($ad.' '.$soyad);
        }

        return $ad ?? $soyad;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     */
    private function pickString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! empty($data[$key])) {
                return (string) $data[$key];
            }
        }

        return null;
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
