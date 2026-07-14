<?php

namespace App\Services\Belsis;

class BelsisIdentityResolver
{
    public function __construct(
        private readonly BelsisBorcSorgulaService $borc,
    ) {}

    public function resolveGensicilNo(string $identityNo, ?string $searchType = null): string
    {
        return $this->borc->resolveGensicilNo($identityNo, $searchType);
    }

    /**
     * TC kimliğe bağlı tüm sicil (gensicilno) listesi.
     *
     * @return array<int, string>
     */
    public function resolveAllGensicilsFromTc(string $tcKimlikNo): array
    {
        return $this->borc->resolveAllGensicilsFromTc($tcKimlikNo);
    }
}
