<?php

namespace App\Exceptions;

use RuntimeException;

/** Aturan alur dilanggar (mis. status tidak sesuai). Dijawab 422 oleh REST API. */
class WorkflowException extends RuntimeException
{
}
