<?php

namespace App\Tests\Unit\Service;

use App\Contracts\Client\OpenMeteoClientInterface;
use App\Entity\Excursion;
use App\Exception\ExcursionBusyException;
use App\Http\Requests\BookingExcursionRequest;
use App\Service\Cache\CachedForecastService;
use App\Service\ExcursionService;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Reproducing a lock wait timeout on a real database needs two transactions running in parallel, which a
 * single PHPUnit process cannot do. Here the entity manager fails the booking transaction as MySQL would,
 * and the test checks how the service reacts to it.
 */
#[CoversMethod(ExcursionService::class, 'bookExcursion')]
class ExcursionServiceBookExcursionLockTest extends TestCase
{
    /**
     * Given a booking whose transaction fails because the excursion cannot be locked (lock wait timeout or deadlock),
     * when the excursion is booked,
     * then ExcursionBusyException (503) is thrown, keeping the database error as previous exception.
     */
    #[Test]
    #[DataProvider('lock_failures')]
    public function throws_excursion_busy_when_the_excursion_cannot_be_locked(RetryableException $failure): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($this->createStub(Connection::class));
        $entityManager->method('wrapInTransaction')->willThrowException($failure);

        $service = new ExcursionService(
            $this->createStub(OpenMeteoClientInterface::class),
            $this->createStub(CachedForecastService::class),
            $entityManager,
        );
        $service->setLogger(new NullLogger());

        try {
            $service->bookExcursion($this->excursion(), new BookingExcursionRequest('John Doe', 'john.doe@example.com', 2));
            $this->fail('The booking did not fail.');
        } catch (ExcursionBusyException $e) {
            $this->assertSame(503, $e->getCode());
        }
    }

    /**
     * @return iterable<string, array{RetryableException}>
     */
    public static function lock_failures(): iterable
    {
        yield 'lock wait timeout' => [
            new LockWaitTimeoutException(self::driverException('Lock wait timeout exceeded', 1205, 'HY000'), null),
        ];
        yield 'deadlock' => [
            new DeadlockException(self::driverException('Deadlock found when trying to get lock', 1213, '40001'), null),
        ];
    }

    /**
     * Builds the low-level error the MySQL driver would raise.
     */
    protected static function driverException(string $message, int $code, string $sqlState): DriverException
    {
        return new class ($message, $code, $sqlState) extends \Exception implements DriverException {
            public function __construct(string $message, int $code, private readonly string $sqlState)
            {
                parent::__construct($message, $code);
            }

            public function getSQLState(): ?string
            {
                return $this->sqlState;
            }
        };
    }

    protected function excursion(): Excursion
    {
        return new Excursion(
            title: 'Etna sunrise',
            description: 'Guided trek to the summit craters.',
            city: 'Catania',
            country: 'IT',
            date: new DateTime('today +7 days'),
            capacity: 10,
            latitude: 37.49223,
            longitude: 15.07041,
        );
    }
}
