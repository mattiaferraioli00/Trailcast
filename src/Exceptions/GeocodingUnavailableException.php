<?php

namespace App\Exceptions;


use Exception;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(503, [
    'Retry-After' => 10,
])]
class GeocodingUnavailableException extends Exception
{
}
