<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class Coordinates
{
    public float $longitude;

    public float $latitude;


    public function __construct(
        float $latitude,
        float $longitude
    )
    {
        $this->longitude = $longitude;
        $this->latitude = $latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

}
