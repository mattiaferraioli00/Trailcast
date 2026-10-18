<?php

namespace App\Service\Cache;

use App\Contracts\Client\OpenMeteoClientInterface;
use App\Dto\Coordinates;
use App\Dto\Forecast;
use App\Exception\OpenMeteo\OpenMeteoUnavailableException;
use App\Traits\LoggerTrait;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CachedForecastService
{
    use LoggerTrait;

    /**
     * Seconds the element lives in cache.
     */
    protected const CACHE_TTL = 3600;

    /**
     * Cache key of the flag that marks Open-Meteo as down.
     */
    protected const PROVIDER_DOWN_KEY = 'forecast_provider_down';

    /**
     * Seconds the flag lives after a failure: until it expires, Open-Meteo is not called.
     */
    protected const PROVIDER_DOWN_TTL = 30;

    public function __construct(
        protected readonly OpenMeteoClientInterface $openMeteoClient,
        protected readonly CacheInterface $cache,
        protected readonly CacheItemPoolInterface $cachePool
    ) {
    }

    /**
     * Returns the daily weather forecast of a location, from cache when available.
     *
     * The whole forecast window is fetched with a single request and cached per location and day
     * for CACHE_TTL seconds, so excursions in the same place share one entry whatever their date.
     * Early expiration is disabled (beta = 0): an entry is recomputed only after it expires, so a
     * provider outage never discards a forecast that is still valid.
     *
     * Failures are propagated and never cached, so the next call retries.
     *
     * @param Coordinates $coordinates Location to get the forecast for.
     *
     * @return array<string, Forecast> Forecasts indexed by local date (Y-m-d), starting from today.
     *
     * @throws InvalidArgumentException When the cache key is invalid.
     */
    public function getDailyForecast(Coordinates $coordinates): array
    {
        $key = $this->cacheKey($coordinates);

        return $this->cache->get($key, function (ItemInterface $item) use ($coordinates, $key): array {
                $item->expiresAfter(self::CACHE_TTL);

            $this->logger->debug('Forecast cache miss.', ['cache_key' => $key]);

            return $this->fetchUnlessProviderDown($coordinates);
        }, 0);
    }

    /**
     * Calls Open-Meteo, unless a recent failure marked it as down (a minimal circuit breaker).
     *
     * After a failure a flag is cached for PROVIDER_DOWN_TTL seconds. While it exists, every cache miss
     * fails at once instead of waiting for the HTTP timeout, both in the same request (the other
     * locations of the page) and in the following ones. When the flag expires, the next miss calls
     * Open-Meteo again: a success closes the circuit, a failure sets the flag for another window.
     *
     * The flag lives in cache.app (Valkey), so it is shared by every application instance. It is only
     * read on a cache miss, so forecasts already cached keep being served while Open-Meteo is down.
     *
     * @param Coordinates $coordinates Location to get the forecast for.
     *
     * @return array<string, Forecast> Forecasts indexed by local date (Y-m-d), starting from today.
     *
     * @throws OpenMeteoUnavailableException When Open-Meteo is marked as down or the call fails.
     * @throws InvalidArgumentException      When the flag key is invalid.
     */
    protected function fetchUnlessProviderDown(Coordinates $coordinates): array
    {
        if ($this->cachePool->hasItem(self::PROVIDER_DOWN_KEY)) {
            throw new OpenMeteoUnavailableException('Open-Meteo is marked as down after a recent failure.');
        }

        try {
            return $this->openMeteoClient->fetchDailyForecast($coordinates);
        } catch (OpenMeteoUnavailableException $e) {
            $flag = $this->cachePool
                ->getItem(self::PROVIDER_DOWN_KEY)
                ->set(true)
                ->expiresAfter(self::PROVIDER_DOWN_TTL);

            $this->cachePool->save($flag);

            $this->logger->warning('Open-Meteo marked as down.', ['seconds' => self::PROVIDER_DOWN_TTL]);

            throw $e;
        }
    }

    /**
     * Builds the cache key of a location's forecast series.
     *
     * The key contains:
     * - today's date, because the series starts from the day it is downloaded;
     * - the coordinates rounded to two decimals (~1 km), so nearby excursions share the same entry.
     *
     * @return string Cache key, e.g. "forecast_2026-10-15_45.85_9.39".
     */
    protected function cacheKey(Coordinates $coordinates): string
    {
        return sprintf(
            'forecast_%s_%s',
            (new \DateTime())->format('Y-m-d'),
            $coordinates->roundedKey(),
        );
    }
}
