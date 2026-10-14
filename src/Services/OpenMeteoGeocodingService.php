<?php

namespace App\Services;

use App\Contracts\Services\OpenMeteoServiceInterface;
use App\Dto\Coordinates;
use App\Exceptions\GeocodingUnavailableException;
use App\Exceptions\LocationNotFoundException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenMeteoGeocodingService implements OpenMeteoServiceInterface
{
    public function __construct(
        protected readonly HttpClientInterface $openMeteoGeocodingClient,
        protected readonly LoggerInterface $logger,
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function locateCoordinates(string $city, string $country): Coordinates
    {
        try {
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

            throw new GeocodingUnavailableException('Open-Meteo geocoding is unavailable.');
        }

        $result = $data['results'][0] ?? null;

        if (is_null($result)) {
            $this->logger->warning('Open-Meteo geocoding returned no results.', [
                'city' => $city,
                'country' => $country,
            ]);

            throw LocationNotFoundException::forCity($city, $country);
        }

        return new Coordinates(
            latitude: (float) $result['latitude'],
            longitude: (float) $result['longitude']
        );
    }
}
