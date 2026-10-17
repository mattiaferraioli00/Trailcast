<?php

namespace App\Tests\Application\Services\ExcursionService;

use App\Tests\Application\ApplicationTestCase;
use App\Contracts\Services\ExcursionServiceInterface;
use App\Entity\Bookings;
use App\Entity\Excursion;
use App\Exceptions\NotEnoughSeatsException;
use App\Http\Requests\BookingExcursionRequest;
use App\Services\ExcursionService;
use DateTime;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;

#[CoversMethod(ExcursionService::class, 'bookExcursion')]
class ExcursionServiceBookExcursionTest extends ApplicationTestCase
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
     * Given an excursion with free seats,
     * when seats are booked,
     * then the booking is stored with the customer data and the seats are taken from the excursion.
     */
    #[Test]
    public function stores_the_booking_and_takes_the_seats(): void
    {
        $excursion = $this->createExcursion(['capacity' => 10]);

        $booking = $this->service->bookExcursion($excursion, $this->request(3));

        $this->entityManager->clear();
        $stored = $this->entityManager->find(Bookings::class, $booking->getId());

        $this->assertNotNull($stored);
        $this->assertSame('John Doe', $stored->getName());
        $this->assertSame('john.doe@example.com', $stored->getEmail());
        $this->assertSame(3, $stored->getSeats());
        $this->assertSame($excursion->getId(), $stored->getExcursion()->getId());
        $this->assertSame(7, $this->entityManager->find(Excursion::class, $excursion->getId())->getSeatsAvailable());
    }

    /**
     * Given an excursion with exactly the requested seats left,
     * when they are booked,
     * then the booking is accepted and no seat is left.
     */
    #[Test]
    public function accepts_a_booking_for_the_last_seats(): void
    {
        $excursion = $this->createExcursion(['capacity' => 10, 'seatsAvailable' => 4]);

        $this->service->bookExcursion($excursion, $this->request(4));

        $this->assertSame(0, $excursion->getSeatsAvailable());
    }

    /**
     * Given an excursion booked twice,
     * when the second booking asks for more than what is left,
     * then it is rejected and the seats taken by the first one stay taken.
     */
    #[Test]
    public function never_sells_more_seats_than_the_capacity(): void
    {
        $excursion = $this->createExcursion(['capacity' => 5]);
        $this->service->bookExcursion($excursion, $this->request(3));

        try {
            $this->service->bookExcursion($excursion, $this->request(3));
            $this->fail('The booking over the capacity was not rejected.');
        } catch (NotEnoughSeatsException) {
            $this->assertSame(2, $excursion->getSeatsAvailable());
            $this->assertSame(1, $this->countBookings());
        }
    }

    /**
     * Given an excursion with fewer seats than requested,
     * when seats are booked,
     * then NotEnoughSeatsException carries the excursion and the seats, and nothing is stored.
     */
    #[Test]
    public function throws_when_there_are_not_enough_seats(): void
    {
        $excursion = $this->createExcursion(['capacity' => 10, 'seatsAvailable' => 2]);

        try {
            $this->service->bookExcursion($excursion, $this->request(3));
            $this->fail('The booking over the available seats was not rejected.');
        } catch (NotEnoughSeatsException $e) {
            $this->assertSame(['excursionId' => $excursion->getId(), 'seats' => 3], $e->getParams());
        }

        $this->assertSame(2, $excursion->getSeatsAvailable());
        $this->assertSame(0, $this->countBookings());
    }

    /**
     * Given an excursion whose date has passed,
     * when seats are booked,
     * then NotEnoughSeatsException is thrown even if seats are left, and nothing is stored.
     */
    #[Test]
    public function throws_when_the_excursion_is_in_the_past(): void
    {
        $excursion = $this->createExcursion(['date' => new DateTime('-1 day')]);

        $this->expectException(NotEnoughSeatsException::class);

        try {
            $this->service->bookExcursion($excursion, $this->request(1));
        } finally {
            $this->assertSame(0, $this->countBookings());
        }
    }

    /**
     * Given an excursion loaded when it had free seats, whose seats were then taken by another process,
     * when seats are booked,
     * then the availability is checked against the stored value, not the stale one in memory.
     */
    #[Test]
    public function checks_the_seats_against_the_latest_stored_value(): void
    {
        $excursion = $this->createExcursion(['capacity' => 10]);
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE excursion SET seats_available = 1 WHERE id = ?',
            [$excursion->getId()],
        );

        $this->expectException(NotEnoughSeatsException::class);

        $this->service->bookExcursion($excursion, $this->request(2));
    }

    /**
     * Builds a booking request of John Doe.
     *
     * @param int $seats Number of seats to book.
     *
     * @return BookingExcursionRequest The request handed to the service.
     */
    protected function request(int $seats): BookingExcursionRequest
    {
        return new BookingExcursionRequest('John Doe', 'john.doe@example.com', $seats);
    }
}
