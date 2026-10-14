<?php

namespace App\Dto;

class Forecast
{
    private readonly ?int $weatherCode;
    private readonly ?float $temperatureMax;
    private readonly ?float $temperatureMin;
    private readonly ?int $precipitationProbability;

    public function __construct(
        ?int $weatherCode,
        ?float $temperatureMax,
        ?float $temperatureMin,
        ?int $precipitationProbability)
    {
        $this->weatherCode = $weatherCode;
        $this->temperatureMax = $temperatureMax;
        $this->temperatureMin = $temperatureMin;
        $this->precipitationProbability = $precipitationProbability;
    }

    public function getWeatherCode(): ?int
    {
        return $this->weatherCode;
    }

    public function getTemperatureMax(): ?float
    {
        return $this->temperatureMax;
    }

    public function getTemperatureMin(): ?float
    {
        return $this->temperatureMin;
    }

    public function getPrecipitationProbability(): ?int
    {
        return $this->precipitationProbability;
    }


}
