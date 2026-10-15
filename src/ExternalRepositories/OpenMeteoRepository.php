<?php

namespace App\ExternalRepositories;

use App\Contracts\ExternalRepositories\OpenMeteoRepositoryInterface;
use App\Dto\Excursion\Coordinates;
use App\Dto\Excursion\Forecast;
use App\Exceptions\LocationNotFoundException;
use App\Exceptions\OpenMeteo\GeocodingUnavailableException;
use App\Exceptions\OpenMeteo\OpenMeteoUnavailableException;
use App\Exceptions\OpenMeteo\OpenMeteoUnprocessableException;
use App\Traits\LoggerTrait;
use Exception;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenMeteoRepository implements OpenMeteoRepositoryInterface
{
    use LoggerTrait;

    protected const FORECAST_DAYS = 16;
    protected const CACHE_TTL = 3600;

    public function __construct(
        protected readonly HttpClientInterface $openMeteoGeocodingClient,
        protected readonly HttpClientInterface $openMeteoForecastClient,
        protected readonly CacheInterface $cache,
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function geocode(
        string $city,
        string $country,
    ): Coordinates
    {
        try {
            // It could be separated connector for this, but it's not necessary for now.
            $data = $this->openMeteoGeocodingClient->request('GET', 'search', [
                'query' => [
                    'name' => $city,
                    'countryCode' => $country,
                    'count' => 1,
                    'language' => 'it',
                    'format' => 'json',
                ],
            ])->toArray();
        } catch (Exception $e) {
            $this->logger->error('Open-Meteo geocoding request failed.', [
                'city' => $city,
                'country' => $country,
                'exception' => $e,
            ]);

            throw new GeocodingUnavailableException();
        }

        $result = $data['results'][0] ?? null;

        if (is_null($result)) {
            $this->logger->warning('Open-Meteo geocoding returned no results.', [
                'city' => $city,
                'country' => $country,
            ]);

            throw new LocationNotFoundException(
                message: "No location found for $city and $country", city: $city, country: $country);
        }

        return new Coordinates(
            latitude: (float) $result['latitude'],
            longitude: (float) $result['longitude']
        );
    }

    /**
     * @inheritDoc
     */
    public function fetchDailyForecast(Coordinates $coordinates): array
    {
        $this->logger->debug('Forecast cache miss, requesting Open-Meteo.', [
            'latitude' => $coordinates->getLatitude(),
            'longitude' => $coordinates->getLongitude(),
        ]);

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
            throw new OpenMeteoUnavailableException();
        }

        $daily = $data['daily'] ?? null;

        if (!is_array($daily) || !is_array($daily['time'] ?? null)) {
            return [];
        }

        $forecasts = [];
        foreach ($daily['time'] as $index => $date) {
            $forecasts[$date] = new Forecast(
                weatherCode: $daily['weather_code'][$index] ?? null,
                temperatureMax: $daily['temperature_2m_max'][$index] ?? null,
                temperatureMin: $daily['temperature_2m_min'][$index] ?? null,
                precipitationProbability: $daily['precipitation_probability_max'][$index] ?? null,
            );
        }

        return $forecasts;
    }

}
