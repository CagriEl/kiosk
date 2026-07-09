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
     * @return array<int, array{id: int, name: string}>
     */
    public function getOdemeSekilleri(): array
    {
        $result = $this->client->callTahsilat('odemeSekilleri', $this->auth->baseParams());
        $items = $this->normalizeList($result['odemeSekilleriListesi']['odemeSekilleri'] ?? $result['odemeSekilleriListesi'] ?? []);

        return array_values(array_filter(array_map(function (array $item) {
            $id = (int) ($item['odemeSekliID'] ?? $item['odemeSekliId'] ?? $item['id'] ?? 0);
            $name = (string) ($item['odemeSekliAdi'] ?? $item['odemeSekli'] ?? $item['adi'] ?? '');

            if ($id === 0 && $name === '') {
                return null;
            }

            return ['id' => $id, 'name' => $name];
        }, $items)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getKdvHesaplari(): array
    {
        $result = $this->client->callTahsilat('kdvHesaplari', $this->auth->baseParams());

        return $this->normalizeList($result['kdvHesaplariListesi']['kdvHesaplari'] ?? $result['kdvHesaplariListesi'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getKdvOranlari(): array
    {
        $result = $this->client->callTahsilat('kdvOranlari', $this->auth->baseParams());

        return $this->normalizeList($result['kdvOranlariListesi']['kdvOranlari'] ?? $result['kdvOranlariListesi'] ?? []);
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
