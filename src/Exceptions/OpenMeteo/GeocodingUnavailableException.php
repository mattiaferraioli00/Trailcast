<?php

namespace App\Exceptions\OpenMeteo;


use App\Exceptions\TException;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Throwable;

#[WithHttpStatus(503, [
    'Retry-After' => 10,
])]
class GeocodingUnavailableException extends TException
{

    public function __construct(
        string $message = "The geocoding service is temporarily unavailable. Please try again later.",
        int $code = 503,
        ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
