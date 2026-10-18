<?php

namespace App\Exception;

use App\Entity\Excursion;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Throwable;

#[WithHttpStatus(503)]
class ExcursionBusyException extends ApiException
{
    public function __construct(
        string $message = "The excursion is being booked by other customers. Please try again.",
        int $code = 503,
        ?Throwable $previous = null,
        ?Excursion $excursion = null,
    )
    {
        parent::__construct($message, $code, $previous);

        $this->addParam("excursion_id", $excursion?->getId());
    }
}
