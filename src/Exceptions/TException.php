<?php

namespace App\Exceptions;

use Exception;

class TException extends Exception
{

    public array $params = [];

    public function addParam($key, $value): void
    {
        if (!is_null($value)) {
            $this->params[$key] = $value;
        }
    }

    public function getParams(): array
    {
        return $this->params;
    }
}
