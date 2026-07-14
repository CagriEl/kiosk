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
     * @return array<int, string>
     */
    public function resolveAllGensicilsFromTc(string $tcKimlikNo): array
    {
        return $this->borc->resolveAllGensicilsFromTc($tcKimlikNo);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolveAccountsFromTc(string $tcKimlikNo): array
    {
        return $this->borc->resolveAccountsFromTc($tcKimlikNo);
    }
}
