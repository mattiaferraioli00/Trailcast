<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
class NotEnoughSeatsException extends Exception
{
}
