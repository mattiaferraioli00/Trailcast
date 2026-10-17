<?php

namespace App\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
readonly class ResponseStatus
{
    public int $code;

    public function __construct(int $code)
    {
        $this->code = $code;
    }
}
