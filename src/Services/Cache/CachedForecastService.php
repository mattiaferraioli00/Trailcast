<?php

namespace App\Services\Cache;

use App\Contracts\ExternalRepositories\OpenMeteoRepositoryInterface;
use App\Dto\Excursion\Coordinates;
use App\Dto\Excursion\Forecast;
use App\Exceptions\OpenMeteo\OpenMeteoUnavailableException;
use App\Exceptions\OpenMeteo\OpenMeteoUnprocessableException;
use App\Traits\LoggerTrait;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CachedForecastService
{
    use LoggerTrait;

    private const CACHE_KEY_VERSION = 'v1';
    private const CACHE_TTL = 3600;
    private const TIMEZONE = 'Europe/Rome';

    public function __construct(
        protected readonly OpenMeteoRepositoryInterface $openMeteoRepository,
        protected readonly CacheInterface $cache,
    ) {
    }

    /**
     * Returns the daily weather forecast for the given location.
     *
     * The whole forecast window is fetched with a single request and cached per
     * location and day for CACHE_TTL seconds, so excursions in the same place
     * share one cache entry whatever their date.
     *
     * Failures are not propagated: if Open-Meteo is unreachable or returns an
     * unexpected payload, the error is logged and an empty array is returned.
     * Failed requests are never cached, so the next call retries.
     *
     * @param Coordinates $coordinates Location to get the forecast for.
     * @return array<string, Forecast> Forecasts indexed by date (Y-m-d); empty if the forecast is unavailable.
     * @throws InvalidArgumentException
     */
    public function getDailyForecast(Coordinates $coordinates): array
    {
        $key = $this->cacheKey($coordinates);

        try {
            return $this->cache->get($key, function (ItemInterface $item) use ($coordinates, $key): array {
                $item->expiresAfter(self::CACHE_TTL);

                $this->logger->debug('Forecast cache miss.', ['cache_key' => $key]);

                return $this->openMeteoRepository->fetchDailyForecast($coordinates);
            });
        } catch (OpenMeteoUnavailableException | OpenMeteoUnprocessableException $e) {
            $this->logger->warning('Forecast unavailable.', ['cache_key' => $key, 'exception' => $e]);
            // TODO: se c'è tempo capire come gestire meglio
            return [];
        }
    }

    /**
     * Builds the cache key for a location's forecast series.
     *
     * The key contains today's date because the series starts from the day it is
     * downloaded, and coordinates rounded to two decimals (~1 km) so that nearby
     * excursions share the same entry. Bump CACHE_KEY_VERSION whenever the
     * requested variables or the Forecast class change.
     */
    private function cacheKey(Coordinates $coordinates): string
    {
        return sprintf(
            'forecast_%s_%s_%.2f_%.2f',
            self::CACHE_KEY_VERSION,
            (new \DateTime())->format('Y-m-d'),
            $coordinates->getLatitude(),
            $coordinates->getLongitude(),
        );
    }
}
