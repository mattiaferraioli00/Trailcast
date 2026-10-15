<?php

namespace App\Dto;

use App\Constants\ForecastStatus;
use App\Entity\Excursion;
use DateTimeImmutable;

readonly class ExcursionForecast
{
    private Excursion $excursion;
    private ForecastStatus $status;
    private ?Forecast $forecast;
    private ?DateTimeImmutable $availableFrom;

    private function __construct(
        Excursion          $excursion,
        ForecastStatus     $status,
        ?Forecast          $forecast,
        ?DateTimeImmutable $availableFrom,
    ) {
        $this->excursion = $excursion;
        $this->status = $status;
        $this->forecast = $forecast;
        $this->availableFrom = $availableFrom;
    }

    /**
     * The forecast of the excursion day is available.
     *
     * @param Excursion $excursion Excursion the forecast refers to.
     * @param Forecast  $forecast  Forecast of the excursion day.
     */
    public static function available(Excursion $excursion, Forecast $forecast): self
    {
        return new self($excursion, ForecastStatus::Available, $forecast, null);
    }

    /**
     * The excursion date is beyond the forecast window: the forecast does not exist yet.
     *
     * @param Excursion          $excursion     Excursion the forecast refers to.
     * @param DateTimeImmutable $availableFrom First day the forecast of the excursion day will be available.
     */
    public static function notYetAvailable(Excursion $excursion, DateTimeImmutable $availableFrom): self
    {
        return new self($excursion, ForecastStatus::NotYetAvailable, null, $availableFrom);
    }

    /**
     * The forecast could not be obtained (Open-Meteo unreachable or unexpected response).
     *
     * @param Excursion $excursion Excursion the forecast refers to.
     */
    public static function unavailable(Excursion $excursion): self
    {
        return new self($excursion, ForecastStatus::Unavailable, null, null);
    }

    /**
     * @return Excursion Excursion the forecast refers to.
     */
    public function getExcursion(): Excursion
    {
        return $this->excursion;
    }

    /**
     * @return ForecastStatus Whether the forecast is available, not available yet or unavailable.
     */
    public function getStatus(): ForecastStatus
    {
        return $this->status;
    }

    /**
     * @return Forecast|null Forecast of the excursion day; null unless the status is Available.
     */
    public function getForecast(): ?Forecast
    {
        return $this->forecast;
    }

    /**
     * @return DateTimeImmutable|null First day the forecast will be available; null unless the status is NotYetAvailable.
     */
    public function getAvailableFrom(): ?DateTimeImmutable
    {
        return $this->availableFrom;
    }
}
