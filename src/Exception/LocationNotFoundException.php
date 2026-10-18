<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Throwable;

class LocationNotFoundException extends ApiException
{
    public function __construct(
        string $message = "No location found",
        int $code = 404,
        ?Throwable $previous = null,
        ?string $city = null,
        ?string $country = null
    )
    {
        parent::__construct($message, $code, $previous);

        $this->addParam("city", $city);
        $this->addParam("country", $country);
    }
}
