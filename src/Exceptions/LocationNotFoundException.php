<?php

namespace App\Exceptions;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(404)]
class LocationNotFoundException extends Exception
{
    public static function forCity(string $city, string $country): self
    {
        return new self(sprintf('Nessuna località trovata per "%s, %s".', $city, $country));
    }
}
