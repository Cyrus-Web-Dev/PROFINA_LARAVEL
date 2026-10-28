<?php

namespace App\Exceptions;

use RuntimeException;

/** Kegagalan berkomunikasi dengan PAYBRIDGE. Pesan sudah berbahasa Indonesia dan aman ditampilkan. */
class PayrollException extends RuntimeException
{
    public function __construct(string $message, public int $status = 0, public array $details = [])
    {
        parent::__construct($message, $status);
    }
}
