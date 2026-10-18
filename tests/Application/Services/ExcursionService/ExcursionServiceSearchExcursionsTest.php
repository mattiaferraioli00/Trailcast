<?php

namespace App\Tests\Application\Services\ExcursionService;

use App\Tests\Application\ApplicationTestCase;
use App\Enum\ForecastStatus;
use App\Contracts\Service\ExcursionServiceInterface;
use App\Dto\ExcursionForecast;
use App\Exception\OpenMeteo\OpenMeteoUnavailableException;
use App\Http\Requests\SearchExcursionRequest;
use App\Service\ExcursionService;
use App\Tests\Support\FakeOpenMeteoClient;
use DateTime;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use Psr\Cache\InvalidArgumentException;

#[CoversMethod(ExcursionService::class, 'searchExcursions')]
class ExcursionServiceSearchExcursionsTest extends ApplicationTestCase
{
    protected ExcursionServiceInterface $service;

    /**
     * Boots the kernel, replaces Open-Meteo with the fake and reads the service under test.
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $this->prepareApplication();

        $this->service = static::getContainer()->get(ExcursionServiceInterface::class);
    }

    /**
     * Given past, sold-out and bookable excursions,
     * when the search runs,
     * then only the bookable ones are returned, ordered by date and then id, with the counters of the page.
     */
    #[Test]
    public function returns_the_bookable_excursions_in_order_with_the_page_counters(): void
    {
        $this->createExcursion(['title' => 'Past', 'date' => new DateTime('-1 day')]);
        $this->createExcursion(['title' => 'Sold out', 'seatsAvailable' => 0]);
        $later = $this->createExcursion(['title' => 'Later', 'date' => new DateTime('+5 days')]);
        $first = $this->createExcursion(['title' => 'First', 'date' => new DateTime('+2 days')]);
        $second = $this->createExcursion(['title' => 'Second', 'date' => new DateTime('+2 days')]);

        $result = $this->service->searchExcursions(new SearchExcursionRequest());

        $this->assertSame(
            [$first->getId(), $second->getId(), $later->getId()],
            array_map(fn (ExcursionForecast $item): int => $item->getExcursion()->getId(), $result->getItems()),
        );
        $this->assertSame(3, $result->getTotal());
        $this->assertSame(1, $result->getPage());
        $this->assertSame(20, $result->getPerPage());
        $this->assertSame(1, $result->getTotalPages());
    }

    /**
     * Given more bookable excursions than the page size,
     * when the second page is requested,
     * then it holds the remaining excursions and the total counts every match.
     */
    #[Test]
    public function paginates_the_excursions(): void
    {
        $this->createExcursion(['date' => new DateTime('+1 day')]);
        $this->createExcursion(['date' => new DateTime('+2 days')]);
        $last = $this->createExcursion(['date' => new DateTime('+3 days')]);

        $result = $this->service->searchExcursions(new SearchExcursionRequest(page: 2, perPage: 2));

        $this->assertCount(1, $result->getItems());
        $this->assertSame($last->getId(), $result->getItems()[0]->getExcursion()->getId());
        $this->assertSame(3, $result->getTotal());
        $this->assertSame(2, $result->getTotalPages());
    }

    /**
     * Given no bookable excursion,
     * when the search runs,
     * then the result is empty, with one page, and Open-Meteo is never called.
     */
    #[Test]
    public function returns_an_empty_page_without_calling_open_meteo(): void
    {
        $result = $this->service->searchExcursions(new SearchExcursionRequest());

        $this->assertSame([], $result->getItems());
        $this->assertSame(0, $result->getTotal());
        $this->assertSame(1, $result->getTotalPages());
        $this->assertSame([], $this->openMeteo->forecastCalls);
    }

    /**
     * Given an excursion inside the forecast window,
     * when the search runs,
     * then it carries the forecast of its day.
     */
    #[Test]
    public function attaches_the_forecast_of_the_excursion_day(): void
    {
        $date = new DateTime('+3 days');
        $series = FakeOpenMeteoClient::dailySeries();
        $this->openMeteo->willFetchForecast($series);
        $this->createExcursion(['date' => $date]);

        $item = $this->searchOne();

        $this->assertSame(ForecastStatus::Available, $item->getStatus());
        $this->assertEquals($series[$date->format('Y-m-d')], $item->getForecast());
        $this->assertNull($item->getAvailableFrom());
    }

    /**
     * Given an excursion beyond the last day of the forecast window,
     * when the search runs,
     * then its forecast is not yet available and the result says from which day it will be.
     */
    #[Test]
    public function marks_the_forecast_as_not_yet_available_beyond_the_window(): void
    {
        $date = new DateTime('+30 days');
        $this->createExcursion(['date' => $date]);

        $item = $this->searchOne();

        $this->assertSame(ForecastStatus::NotYetAvailable, $item->getStatus());
        $this->assertNull($item->getForecast());
        $this->assertSame(
            (clone $date)->modify('-15 days')->format('Y-m-d'),
            $item->getAvailableFrom()->format('Y-m-d'),
        );
    }

    /**
     * Given a forecast series that skips the excursion day,
     * when the search runs,
     * then the forecast is unavailable.
     */
    #[Test]
    public function marks_the_forecast_as_unavailable_when_the_day_is_missing_from_the_series(): void
    {
        $series = FakeOpenMeteoClient::dailySeries();
        unset($series[(new DateTime('+3 days'))->format('Y-m-d')]);
        $this->openMeteo->willFetchForecast($series);
        $this->createExcursion(['date' => new DateTime('+3 days')]);

        $this->assertSame(ForecastStatus::Unavailable, $this->searchOne()->getStatus());
    }

    /**
     * Given an Open-Meteo that is down,
     * when the search runs,
     * then the excursions are returned anyway, with an unavailable forecast.
     */
    #[Test]
    public function returns_the_excursions_without_forecast_when_open_meteo_fails(): void
    {
        $this->openMeteo->willFetchForecast(new OpenMeteoUnavailableException());
        $excursion = $this->createExcursion(['date' => new DateTime('+3 days')]);

        $item = $this->searchOne();

        $this->assertSame($excursion->getId(), $item->getExcursion()->getId());
        $this->assertSame(ForecastStatus::Unavailable, $item->getStatus());
        $this->assertNull($item->getForecast());
    }

    /**
     * Given the same search run twice,
     * when the forecast of the location is already cached,
     * then Open-Meteo is called only once.
     */
    #[Test]
    public function calls_open_meteo_once_for_two_searches(): void
    {
        $this->createExcursion(['date' => new DateTime('+3 days')]);

        $this->service->searchExcursions(new SearchExcursionRequest());
        $this->service->searchExcursions(new SearchExcursionRequest());

        $this->assertCount(1, $this->openMeteo->forecastCalls);
    }

    /**
     * Given excursions in the same place (coordinates equal once rounded to two decimals) and in another one,
     * when the search runs,
     * then Open-Meteo is called once per place.
     */
    #[Test]
    public function calls_open_meteo_once_per_location(): void
    {
        $this->createExcursion(['latitude' => 37.4922, 'longitude' => 15.0704]);
        $this->createExcursion(['latitude' => 37.4949, 'longitude' => 15.0711]);
        $this->createExcursion(['latitude' => 45.4642, 'longitude' => 9.1900]);

        $this->service->searchExcursions(new SearchExcursionRequest());

        $this->assertCount(2, $this->openMeteo->forecastCalls);
    }

    /**
     * Given an Open-Meteo that failed and has since recovered,
     * when the "down" flag has expired and the search runs again,
     * then Open-Meteo is called again and the forecast is available: failures are never cached as forecasts.
     */
    #[Test]
    public function calls_open_meteo_again_once_the_down_flag_expires(): void
    {
        $this->openMeteo->willFetchForecast(new OpenMeteoUnavailableException(), FakeOpenMeteoClient::dailySeries());
        $this->createExcursion(['date' => new DateTime('+3 days')]);

        $first = $this->searchOne();
        static::getContainer()->get('cache.app')->clear();
        $second = $this->searchOne();

        $this->assertSame(ForecastStatus::Unavailable, $first->getStatus());
        $this->assertSame(ForecastStatus::Available, $second->getStatus());
        $this->assertCount(2, $this->openMeteo->forecastCalls);
    }

    /**
     * Given an Open-Meteo that fails,
     * when the same search runs again right after,
     * then Open-Meteo is not called again: it is marked as down and the forecast is unavailable at once.
     */
    #[Test]
    public function does_not_call_open_meteo_while_it_is_marked_as_down(): void
    {
        $this->openMeteo->willFetchForecast(new OpenMeteoUnavailableException(), FakeOpenMeteoClient::dailySeries());
        $this->createExcursion(['date' => new DateTime('+3 days')]);

        $first = $this->searchOne();
        $second = $this->searchOne();

        $this->assertSame(ForecastStatus::Unavailable, $first->getStatus());
        $this->assertSame(ForecastStatus::Unavailable, $second->getStatus());
        $this->assertCount(1, $this->openMeteo->forecastCalls);
    }

    /**
     * Runs the search without filters and returns its only item.
     *
     * @return ExcursionForecast The single excursion found, with its forecast.
     *
     * @throws InvalidArgumentException
     */
    protected function searchOne(): ExcursionForecast
    {
        $result = $this->service->searchExcursions(new SearchExcursionRequest());

        $this->assertCount(1, $result->getItems());

        return $result->getItems()[0];
    }
}
