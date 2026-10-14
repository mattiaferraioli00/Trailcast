<?php

namespace App\Dto\Excursion;

class Coordinates
{
    private float $longitude;

    private float $latitude;


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
