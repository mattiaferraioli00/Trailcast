<?php

namespace App\Dto;

readonly class Forecast
{
    protected ?int $weatherCode;
    protected ?float $temperatureMax;
    protected ?float $temperatureMin;
    protected ?int $precipitationProbability;

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

    /**
     * @return int|null Most severe weather condition of the day, as a WMO weather interpretation code
     *                  (e.g. 0 clear sky, 61 slight rain, 95 thunderstorm).
     */
    public function getWeatherCode(): ?int
    {
        return $this->weatherCode;
    }

    /**
     * @return float|null Maximum air temperature of the day at 2 m above ground, in °C.
     */
    public function getTemperatureMax(): ?float
    {
        return $this->temperatureMax;
    }

    /**
     * @return float|null Minimum air temperature of the day at 2 m above ground, in °C.
     */
    public function getTemperatureMin(): ?float
    {
        return $this->temperatureMin;
    }

    /**
     * @return int|null Highest hourly probability of precipitation during the day, in percent (0-100).
     */
    public function getPrecipitationProbability(): ?int
    {
        return $this->precipitationProbability;
    }
}
