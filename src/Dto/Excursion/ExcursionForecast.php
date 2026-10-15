<?php

namespace App\Dto\Excursion;

use App\Costants\ForecastStatus;
use App\Entity\Excursion;

class ExcursionForecast
{
    private readonly Excursion $excursion;
    private readonly ForecastStatus $status;
    private readonly ?Forecast $forecast;
    private readonly ?\DateTimeImmutable $availableFrom;

    private function __construct(
        Excursion $excursion,
        ForecastStatus $status,
        ?Forecast $forecast,
        ?\DateTimeImmutable $availableFrom,
    ) {
        $this->excursion = $excursion;
        $this->status = $status;
        $this->forecast = $forecast;
        $this->availableFrom = $availableFrom;
    }

    public static function available(Excursion $excursion, Forecast $forecast): self
    {
        return new self($excursion, ForecastStatus::Available, $forecast, null);
    }

    public static function notYetAvailable(Excursion $excursion, \DateTimeImmutable $availableFrom): self
    {
        return new self($excursion, ForecastStatus::NotYetAvailable, null, $availableFrom);
    }

    public static function unavailable(Excursion $excursion): self
    {
        return new self($excursion, ForecastStatus::Unavailable, null, null);
    }

    public function getExcursion(): Excursion
    {
        return $this->excursion;
    }

    public function getStatus(): ForecastStatus
    {
        return $this->status;
    }

    public function getForecast(): ?Forecast
    {
        return $this->forecast;
    }

    public function getAvailableFrom(): ?\DateTimeImmutable
    {
        return $this->availableFrom;
    }
}
