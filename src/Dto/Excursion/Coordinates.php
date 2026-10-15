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

    /**
     * Location key rounded to 2 decimals (~1 km): nearby points share
     * the same forecast grid cell, so they share the same key.
     */
    public function roundedKey(): string
    {
        return sprintf('%.2F_%.2F', $this->latitude, $this->longitude);
    }
}
