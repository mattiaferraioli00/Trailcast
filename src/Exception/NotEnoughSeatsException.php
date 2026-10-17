<?php

namespace App\Exception;

use App\Entity\Excursion;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Throwable;

#[WithHttpStatus(409)]
class NotEnoughSeatsException extends ApiException
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

        $this->addParam("excursion_id", $excursion?->getId());
        $this->addParam("seats", $seats);
    }
}
