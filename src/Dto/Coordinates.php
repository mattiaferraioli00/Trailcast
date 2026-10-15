<?php

namespace App\Dto;

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

    /**
     * @return float Longitude in decimal degrees (-180 to 180, negative west of Greenwich).
     */
    public function getLongitude(): float
    {
        return $this->longitude;
    }

    /**
     * @return float Latitude in decimal degrees (-90 to 90, negative south of the equator).
     */
    public function getLatitude(): float
    {
        return $this->latitude;
    }

    /**
     * Location key with both coordinates rounded to two decimals (about 1 km).
     *
     * Weather models have a resolution of several kilometres, so points sharing the same key get
     * practically the same forecast and can share one lookup and one cache entry. The %F format is
     * locale-independent, so the decimal separator is always a dot.
     *
     * @return string Key in the form "<latitude>_<longitude>", e.g. "45.85_9.39".
     */
    public function roundedKey(): string
    {
        return sprintf('%.2F_%.2F', $this->latitude, $this->longitude);
    }
}
