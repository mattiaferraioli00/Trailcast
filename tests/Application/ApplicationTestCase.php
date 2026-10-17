<?php

namespace App\Tests\Application;

use App\Entity\Bookings;
use App\Entity\Excursion;
use App\Entity\User;
use App\Client\OpenMeteoClient;
use App\Tests\Support\FakeOpenMeteoClient;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base class of the Application tests: they all work on the real test database, with Open-Meteo replaced by a fake.
 *
 * Controller tests create the browser with createClient(); service and repository tests only boot the kernel
 * with bootKernel(). Either way, prepareApplication() must be called once the kernel is booted.
 */
abstract class ApplicationTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected FakeOpenMeteoClient $openMeteo;

    protected EntityManagerInterface $entityManager;

    /**
     * Replaces Open-Meteo with the fake and reads the entity manager. Call it after the kernel is booted.
     */
    protected function prepareApplication(): void
    {
        $container = static::getContainer();

        $this->openMeteo = new FakeOpenMeteoClient();
        $container->set(OpenMeteoClient::class, $this->openMeteo);

        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    /**
     * Stores an excursion in the test database.
     *
     * @param array<string, mixed> $overrides Any of: title, description, city, country, date, capacity,
     *                                        latitude, longitude, seatsAvailable.
     *
     * @return Excursion The stored excursion.
     */
    protected function createExcursion(array $overrides = []): Excursion
    {
        $data = array_merge([
            'title' => 'Etna sunrise',
            'description' => 'Guided trek to the summit craters.',
            'city' => 'Catania',
            'country' => 'IT',
            'date' => new DateTime('+7 days'),
            'capacity' => 10,
            'latitude' => 37.49223,
            'longitude' => 15.07041,
            'seatsAvailable' => null,
        ], $overrides);

        $excursion = new Excursion(
            title: $data['title'],
            description: $data['description'],
            city: $data['city'],
            country: $data['country'],
            date: $data['date'],
            capacity: $data['capacity'],
            latitude: $data['latitude'],
            longitude: $data['longitude'],
        );

        if (!is_null($data['seatsAvailable'])) {
            $excursion->setSeatsAvailable($data['seatsAvailable']);
        }

        $this->entityManager->persist($excursion);
        $this->entityManager->flush();

        return $excursion;
    }

    /**
     * Stores a user in the test database.
     *
     * @param list<string> $roles
     *
     * @return User The stored user.
     */
    protected function createUser(string $email, array $roles = [], string $password = 'secret123'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * @return int Number of excursions stored in the test database.
     */
    protected function countExcursions(): int
    {
        return $this->entityManager->getRepository(Excursion::class)->count([]);
    }

    /**
     * @return int Number of bookings stored in the test database.
     */
    protected function countBookings(): int
    {
        return $this->entityManager->getRepository(Bookings::class)->count([]);
    }

    /**
     * Sends a request with a JSON body and, when a user is given, its JWT as bearer token.
     *
     * @param array<string, mixed>|null $payload JSON body; null sends no body.
     * @param User|null                 $user    Authenticated user; null sends the request anonymously.
     */
    protected function callApi(string $method, string $uri, ?array $payload = null, ?User $user = null): void
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        if (!is_null($user)) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->tokenFor($user);
        }

        $this->client->request($method, $uri, [], [], $server, is_null($payload) ? null : json_encode($payload));
    }

    /**
     * Issues a valid JWT for the user, as the login endpoint would.
     *
     * @return string The signed token.
     */
    protected function tokenFor(User $user): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
    }

    /**
     * Decodes the JSON body of the last response.
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    protected function responseBody(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
