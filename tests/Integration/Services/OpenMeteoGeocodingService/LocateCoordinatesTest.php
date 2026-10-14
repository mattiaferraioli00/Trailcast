<?php

namespace App\Tests\Integration\Services\OpenMeteoGeocodingService;

use App\Contracts\Services\OpenMeteoServiceInterface;
use App\Exceptions\GeocodingUnavailableException;
use App\Exceptions\LocationNotFoundException;
use App\Services\OpenMeteoGeocodingService;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversMethod(OpenMeteoGeocodingService::class, 'locateCoordinates')]
class LocateCoordinatesTest extends KernelTestCase
{
    protected MockHttpClient $httpClient;

    protected OpenMeteoServiceInterface $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->httpClient = new MockHttpClient(null, 'https://geocoding-api.open-meteo.com/v1/');
        $container->set('open_meteo_geocoding.client', $this->httpClient);

        $this->service = $container->get(OpenMeteoServiceInterface::class);
    }

    /**
     * Given a provider response with one match for "Catania, IT",
     * when the coordinates are located,
     * then latitude and longitude of the first match are returned.
     */
    #[Test]
    public function returns_coordinates_when_provider_responds_with_a_result(): void
    {
        $this->httpClient->setResponseFactory(new MockResponse($this->geocodingSearchWithResult()));

        $coordinates = $this->service->locateCoordinates('Catania', 'IT');

        $this->assertSame(37.49223, $coordinates->getLatitude());
        $this->assertSame(15.07041, $coordinates->getLongitude());
    }

    /**
     * Given a provider response whose coordinates are whole numbers (decoded by PHP as int),
     * when the coordinates are located,
     * then latitude and longitude are still returned as float.
     */
    #[Test]
    public function casts_integer_coordinates_to_float(): void
    {
        $body = json_encode(['results' => [['latitude' => 38, 'longitude' => 15]]]);
        $this->httpClient->setResponseFactory(new MockResponse($body));

        $coordinates = $this->service->locateCoordinates('Messina', 'IT');

        $this->assertSame(38.0, $coordinates->getLatitude());
        $this->assertSame(15.0, $coordinates->getLongitude());
    }

    /**
     * Given a provider response without any match (missing or empty "results"),
     * when the coordinates are located,
     * then a LocationNotFoundException is thrown.
     */
    #[Test]
    #[DataProvider('responses_without_results')]
    public function throws_location_not_found_when_provider_returns_no_results(string $body): void
    {
        $this->httpClient->setResponseFactory(new MockResponse($body));

        $this->expectException(LocationNotFoundException::class);

        $this->service->locateCoordinates('Nowhereville', 'IT');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function responses_without_results(): iterable
    {
        yield 'results key missing' => ['{"generationtime_ms": 1.2}'];
        yield 'results empty' => ['{"results": [], "generationtime_ms": 1.2}'];
    }

    /**
     * Given a provider that fails (HTTP 4xx/5xx, network error or invalid JSON),
     * when the coordinates are located,
     * then a GeocodingUnavailableException is thrown.
     */
    #[Test]
    #[DataProvider('provider_failures')]
    public function throws_geocoding_unavailable_when_provider_fails(MockResponse $response): void
    {
        $this->httpClient->setResponseFactory($response);

        $this->expectException(GeocodingUnavailableException::class);

        $this->service->locateCoordinates('Catania', 'IT');
    }

    /**
     * @return iterable<string, array{MockResponse}>
     */
    public static function provider_failures(): iterable
    {
        yield 'server error' => [new MockResponse('', ['http_code' => 500])];
        yield 'client error' => [new MockResponse('', ['http_code' => 404])];
        yield 'transport error' => [new MockResponse('', ['error' => 'connection refused'])];
        yield 'invalid json' => [new MockResponse('<html>not json</html>')];
    }

    protected function geocodingSearchWithResult(): string
    {
        return file_get_contents(dirname(__DIR__, 3) . '/Fixtures/open_meteo/geocoding_search_with_result.json');
    }
}
