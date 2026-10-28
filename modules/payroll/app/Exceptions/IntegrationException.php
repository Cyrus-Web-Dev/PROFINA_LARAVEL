<?php

namespace App\Exceptions;

use RuntimeException;

class IntegrationException extends RuntimeException
{
    public function __construct(string $message, public int $status = 0, public array $details = [])
    {
        parent::__construct($message);
    }
}
