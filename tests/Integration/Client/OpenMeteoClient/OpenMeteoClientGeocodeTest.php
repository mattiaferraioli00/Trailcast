<?php

namespace App\Tests\Integration\Client\OpenMeteoClient;

use App\Dto\Coordinates;
use App\Exception\LocationNotFoundException;
use App\Client\OpenMeteoClient;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Calls the real Open-Meteo Geocoding API: it needs network access.
 */
#[CoversMethod(OpenMeteoClient::class, 'geocode')]
class OpenMeteoClientGeocodeTest extends KernelTestCase
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
     * Given a well-known Italian city,
     * when it is geocoded,
     * then the result is a pair of coordinates inside the valid ranges and around Italy.
     */
    #[Test]
    public function returns_coordinates_of_the_requested_city(): void
    {
        $coordinates = $this->client->geocode('Catania', 'IT');

        $this->assertInstanceOf(Coordinates::class, $coordinates);
        $this->assertGreaterThan(35.0, $coordinates->getLatitude());
        $this->assertLessThan(48.0, $coordinates->getLatitude());
        $this->assertGreaterThan(6.0, $coordinates->getLongitude());
        $this->assertLessThan(19.0, $coordinates->getLongitude());
    }

    /**
     * Given the same city written with different case,
     * when it is geocoded,
     * then the coordinates are the same.
     */
    #[Test]
    public function matches_the_city_name_ignoring_case(): void
    {
        $this->assertEquals(
            $this->client->geocode('Catania', 'IT'),
            $this->client->geocode('catania', 'IT'),
        );
    }

    /**
     * Given a name that matches no place,
     * when it is geocoded,
     * then LocationNotFoundException carries the city and the country.
     */
    #[Test]
    public function throws_when_no_location_matches(): void
    {
        try {
            $this->client->geocode('Zzyzxqwvk', 'IT');
            $this->fail('The unknown city was not rejected.');
        } catch (LocationNotFoundException $e) {
            $this->assertSame(['city' => 'Zzyzxqwvk', 'country' => 'IT'], $e->getParams());
        }
    }
}
