<?php

namespace App\Services\Belsis;

use App\Exceptions\BelsisException;

class BelsisIdentityResolver
{
    public function __construct(
        private readonly BelsisBorcSorgulaService $borc,
    ) {}

    public function resolveGensicilNo(string $identityNo, ?string $searchType = null): string
    {
        return $this->borc->resolveGensicilNo($identityNo, $searchType);
    }
}
