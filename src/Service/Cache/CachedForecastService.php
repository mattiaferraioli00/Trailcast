<?php

namespace App\Service\Cache;

use App\Contracts\Client\OpenMeteoClientInterface;
use App\Dto\Coordinates;
use App\Dto\Forecast;
use App\Traits\LoggerTrait;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CachedForecastService
{
    use LoggerTrait;
    protected const CACHE_TTL = 3600;

    public function __construct(
        protected readonly OpenMeteoClientInterface $openMeteoClient,
        protected readonly CacheInterface $cache,
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

            return $this->openMeteoClient->fetchDailyForecast($coordinates);
        }, 0);
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
