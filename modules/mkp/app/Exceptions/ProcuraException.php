<?php

namespace App\Exceptions;

use RuntimeException;

/** Kegagalan berkomunikasi dengan PROCURA. Pesannya sudah dalam bahasa Indonesia dan aman ditampilkan ke pengguna. */
class ProcuraException extends RuntimeException
{
    public function __construct(string $message, public int $status = 0, public array $details = [])
    {
        parent::__construct($message, $status);
    }
}
