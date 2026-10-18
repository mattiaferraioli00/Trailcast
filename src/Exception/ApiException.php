<?php

namespace App\Exception;

use Exception;

class ApiException extends Exception
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
