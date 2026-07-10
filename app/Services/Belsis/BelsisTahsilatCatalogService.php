<?php

namespace App\Services\Belsis;

use App\Services\Belsis\Concerns\NormalizesBelsisLists;

class BelsisTahsilatCatalogService
{
    use NormalizesBelsisLists;

    public function __construct(
        private readonly BelsisSoapClient $client,
        private readonly BelsisAuthService $auth,
    ) {}

    /**
     * WSDL (odemeSekliC): wrapper 'odemeSekliListesi' (tekil "Sekli", çoğul değil),
     * öğe alanları 'ID' / 'kullaniciOdemeSekli' / 'aciklama' (case-sensitive).
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function getOdemeSekilleri(): array
    {
        $result = $this->client->callTahsilat('odemeSekilleri', $this->auth->baseParams());
        $items = $this->normalizeList($result['odemeSekliListesi']['odemeSekilleri'] ?? $result['odemeSekliListesi'] ?? []);

        return array_values(array_filter(array_map(function (array $item) {
            $id = (int) ($item['ID'] ?? $item['odemeSekliID'] ?? $item['id'] ?? 0);
            $name = (string) ($item['aciklama'] ?? $item['odemeSekliAdi'] ?? $item['adi'] ?? '');

            if ($id === 0 && $name === '') {
                return null;
            }

            return ['id' => $id, 'name' => $name];
        }, $items)));
    }

    /**
     * WSDL (kdvHesabiC): wrapper 'kdvHesabiListesi' (tekil "Hesabi", çoğul değil).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getKdvHesaplari(): array
    {
        $result = $this->client->callTahsilat('kdvHesaplari', $this->auth->baseParams());

        return $this->normalizeList($result['kdvHesabiListesi']['kdvHesaplari'] ?? $result['kdvHesabiListesi'] ?? []);
    }

    /**
     * WSDL (kdvOraniC): wrapper 'kdvOraniListesi' (tekil "Orani", çoğul değil).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getKdvOranlari(): array
    {
        $result = $this->client->callTahsilat('kdvOranlari', $this->auth->baseParams());

        return $this->normalizeList($result['kdvOraniListesi']['kdvOranlari'] ?? $result['kdvOraniListesi'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTahakkukTurleri(): array
    {
        $result = $this->client->callTahsilat('tahakkukTurleri', $this->auth->baseParams());

        return $this->normalizeList($result['tahakkukTurListesi']['tahakkukTurleri'] ?? $result['tahakkukTurListesi'] ?? []);
    }
}
