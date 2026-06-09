<?php

namespace App\Exceptions;

use Exception;

class BelsisException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $sonucKodu = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
