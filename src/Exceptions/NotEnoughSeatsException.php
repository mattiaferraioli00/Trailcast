<?php

namespace App\Exceptions;

use App\Entity\Excursion;
use Exception;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Throwable;

#[WithHttpStatus(409)]
class NotEnoughSeatsException extends TException
{
    public function __construct(
        string $message = "Not enough seats available for this excursion.",
        int $code = 409,
        ?Throwable $previous = null,
        ?Excursion $excursion = null,
        ?int $seats = null,
    )
    {
        parent::__construct($message, $code, $previous);

        $this->addParam("excursionId", $excursion->getId());
        $this->addParam("seats", $seats);
    }
}
