<?php

namespace App\Tests\Support;

use App\Contracts\Client\OpenMeteoClientInterface;
use App\Dto\Coordinates;
use App\Dto\Forecast;
use DateTimeImmutable;
use Throwable;

/**
 * Stand-in for Open-Meteo used by the Application tests: it never touches the network.
 *
 * Outcomes are queued with willGeocode() / willFetchForecast(); every call consumes the next one and the
 * last one is repeated forever. Without any queued outcome, geocoding resolves to Catania and the forecast
 * is a full window of sunny days.
 */
class FakeOpenMeteoClient implements OpenMeteoClientInterface
{
    public const FORECAST_DAYS = 16;

    /** @var list<array{string, string}> city and country of every geocode() call. */
    public array $geocodeCalls = [];

    /** @var list<Coordinates> coordinates of every fetchDailyForecast() call. */
    public array $forecastCalls = [];

    /** @var list<Coordinates|Throwable> */
    protected array $geocodeOutcomes = [];

    /** @var list<array<string, Forecast>|Throwable> */
    protected array $forecastOutcomes = [];

    /**
     * Queues what the next geocode() calls do: return the coordinates or throw the exception.
     */
    public function willGeocode(Coordinates|Throwable ...$outcomes): static
    {
        $this->geocodeOutcomes = array_values($outcomes);

        return $this;
    }

    /**
     * Queues what the next fetchDailyForecast() calls do: return the series or throw the exception.
     *
     * @param array<string, Forecast>|Throwable ...$outcomes
     */
    public function willFetchForecast(array|Throwable ...$outcomes): static
    {
        $this->forecastOutcomes = array_values($outcomes);

        return $this;
    }

    /**
     * Builds a daily series starting from today, one sunny day after the other.
     *
     * @param int $days Number of days of the series.
     *
     * @return array<string, Forecast> Forecasts indexed by date (Y-m-d).
     */
    public static function dailySeries(int $days = self::FORECAST_DAYS): array
    {
        $series = [];
        $today = new DateTimeImmutable('today');

        for ($i = 0; $i < $days; $i++) {
            $series[$today->modify("+$i days")->format('Y-m-d')] = new Forecast(
                weatherCode: 0,
                temperatureMax: 20.0 + $i,
                temperatureMin: 10.0 + $i,
                precipitationProbability: 10,
            );
        }

        return $series;
    }

    /**
     * @inheritDoc
     */
    public function geocode(string $city, string $country): Coordinates
    {
        $this->geocodeCalls[] = [$city, $country];

        $outcome = $this->next($this->geocodeOutcomes) ?? new Coordinates(37.49223, 15.07041);

        if ($outcome instanceof Throwable) {
            throw $outcome;
        }

        return $outcome;
    }

    /**
     * @inheritDoc
     */
    public function fetchDailyForecast(Coordinates $coordinates): array
    {
        $this->forecastCalls[] = $coordinates;

        $outcome = $this->next($this->forecastOutcomes) ?? self::dailySeries();

        if ($outcome instanceof Throwable) {
            throw $outcome;
        }

        return $outcome;
    }

    /**
     * Takes the next queued outcome, keeping the last one in the queue so that it keeps being repeated.
     */
    protected function next(array &$outcomes): mixed
    {
        if (count($outcomes) > 1) {
            return array_shift($outcomes);
        }

        return $outcomes[0] ?? null;
    }
}
