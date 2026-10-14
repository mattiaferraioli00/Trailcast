<?php

namespace App\Services;

use App\Dto\Coordinates;
use App\Dto\Forecast;
use App\Exceptions\OpenMeteo\OpenMeteoUnavailableException;
use App\Exceptions\OpenMeteo\OpenMeteoUnprocessableException;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenMeteoForecastService
{
    protected const FORECAST_DAYS = 16;
    protected const CACHE_TTL = 3600;

    public function __construct(
        protected readonly HttpClientInterface $openMeteoForecastClient,
        protected readonly LoggerInterface $logger,
        protected readonly CacheInterface $cache,
    )
    {
    }

    /** @return array<string, Forecast> indicizzato per data Y-m-d; vuoto se il meteo non è disponibile
     * @throws InvalidArgumentException
     */
    public function getWeatherData(Coordinates $coordinates): array
    {
        $key = sprintf(
            'forecast_v1_%s_%.2f_%.2f',
            (new \DateTime('now'))->format('Y-m-d'),
            $coordinates->getLatitude(),
            $coordinates->getLongitude(),
        );

        try {
            return $this->cache->get($key, function (ItemInterface $item) use ($coordinates): array {
                $item->expiresAfter(self::CACHE_TTL);

                return $this->fetch($coordinates);
            });
        } catch (OpenMeteoUnavailableException $e) {
            $this->logger->warning('Forecast unavailable.', ['cache_key' => $key, 'exception' => $e]);

            return [];
        }
    }

    /**
     * @return array<string, Forecast>
     * @throws OpenMeteoUnavailableException
     * @throws OpenMeteoUnprocessableException
     */
    private function fetch(Coordinates $coordinates): array
    {
        try {
            $data = $this->openMeteoForecastClient->request('GET', 'forecast', [
                'query' => [
                    'latitude' => $coordinates->getLatitude(),
                    'longitude' => $coordinates->getLongitude(),
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
                    'forecast_days' => self::FORECAST_DAYS,
                    'timezone' => 'auto',
                ],
            ])->toArray();
        } catch (Exception $e) {
            throw new OpenMeteoUnavailableException('Forecast request failed.', previous: $e);
        }

        $daily = $data['daily'] ?? null;
        if (!is_array($daily) || !is_array($daily['time'] ?? null)) {
            throw new OpenMeteoUnprocessableException('Unexpected forecast response format.');
        }

        $forecasts = [];
        foreach ($daily['time'] as $i => $date) {
            $forecasts[$date] = new Forecast(
                weatherCode: $daily['weather_code'][$i] ?? null,
                temperatureMax: $daily['temperature_2m_max'][$i] ?? null,
                temperatureMin: $daily['temperature_2m_min'][$i] ?? null,
                precipitationProbability: $daily['precipitation_probability_max'][$i] ?? null,
            );
        }

        return $forecasts;
    }

}
