<?php

namespace App\Exception\OpenMeteo;

use App\Exception\ApiException;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Throwable;

#[WithHttpStatus(503)]
class GeocodingUnavailableException extends ApiException
{

    public function __construct(
        string $message = "The geocoding service is temporarily unavailable. Please try again later.",
        int $code = 503,
        ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
