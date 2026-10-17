<?php

namespace App\Tests\Integration\Client\OpenMeteoClient;

use App\Dto\Coordinates;
use App\Dto\Forecast;
use App\Exception\OpenMeteo\OpenMeteoUnavailableException;
use App\Client\OpenMeteoClient;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Calls the real Open-Meteo Forecast API: it needs network access. Only the shape of the data is asserted,
 * never the weather itself.
 */
#[CoversMethod(OpenMeteoClient::class, 'fetchDailyForecast')]
class OpenMeteoClientFetchDailyForecastTest extends KernelTestCase
{
    protected OpenMeteoClient $client;

    /**
     * Boots the kernel and reads the client wired with the real HTTP clients.
     */
    protected function setUp(): void
    {
        self::bootKernel();

        $this->client = static::getContainer()->get(OpenMeteoClient::class);
    }

    /**
     * Given the coordinates of a city,
     * when the daily forecast is fetched,
     * then it holds the whole forecast window, one entry per consecutive day.
     *
     * @throws OpenMeteoUnavailableException
     * @throws Exception
     */
    #[Test]
    public function returns_one_forecast_per_day_of_the_window(): void
    {
        $series = $this->client->fetchDailyForecast(new Coordinates(37.49223, 15.07041));

        $this->assertCount(OpenMeteoClient::FORECAST_DAYS, $series);

        $dates = array_keys($series);
        foreach ($dates as $index => $date) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
            $this->assertSame(
                (new DateTimeImmutable($dates[0]))->modify("+$index days")->format('Y-m-d'),
                $date,
            );
        }
    }

    /**
     * Given the forecast of a location,
     * when its first day is compared with the current date,
     * then it starts today in the location's timezone (at most one day away from the server date).
     */
    #[Test]
    public function starts_from_today_in_the_location_timezone(): void
    {
        $series = $this->client->fetchDailyForecast(new Coordinates(37.49223, 15.07041));

        $first = new DateTimeImmutable(array_key_first($series));
        $days = abs((int) $first->diff(new DateTimeImmutable('today'))->format('%r%a'));

        $this->assertLessThanOrEqual(1, $days);
    }

    /**
     * Given the forecast of a location,
     * when its values are read,
     * then every day is a Forecast whose values, when present, are in a plausible range.
     */
    #[Test]
    public function returns_forecasts_with_plausible_values(): void
    {
        $series = $this->client->fetchDailyForecast(new Coordinates(37.49223, 15.07041));

        foreach ($series as $forecast) {
            $this->assertInstanceOf(Forecast::class, $forecast);

            if (!is_null($forecast->getWeatherCode())) {
                $this->assertGreaterThanOrEqual(0, $forecast->getWeatherCode());
                $this->assertLessThanOrEqual(99, $forecast->getWeatherCode());
            }

            foreach ([$forecast->getTemperatureMax(), $forecast->getTemperatureMin()] as $temperature) {
                if (!is_null($temperature)) {
                    $this->assertGreaterThan(-90.0, $temperature);
                    $this->assertLessThan(60.0, $temperature);
                }
            }

            if (!is_null($forecast->getTemperatureMax()) && !is_null($forecast->getTemperatureMin())) {
                $this->assertGreaterThanOrEqual($forecast->getTemperatureMin(), $forecast->getTemperatureMax());
            }

            if (!is_null($forecast->getPrecipitationProbability())) {
                $this->assertGreaterThanOrEqual(0, $forecast->getPrecipitationProbability());
                $this->assertLessThanOrEqual(100, $forecast->getPrecipitationProbability());
            }
        }
    }
}
