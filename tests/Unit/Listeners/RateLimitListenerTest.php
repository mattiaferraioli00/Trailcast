<?php


namespace App\Tests\Unit\Listeners;

use App\Listeners\RateLimitListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(RateLimitListener::class)]
class RateLimitListenerTest extends TestCase
{
    protected const LIMIT = 2;

    protected RateLimitListener $listener;

    protected function setUp(): void
    {
        $factory = new RateLimiterFactory(
            ['id' => 'api', 'policy' => 'fixed_window', 'limit' => self::LIMIT, 'interval' => '1 minute'],
            new InMemoryStorage(),
        );

        $this->listener = new RateLimitListener($factory);
    }

    /**
     * Given a client that has used up its requests in the current window,
     * when it sends another request,
     * then it is rejected with 429.
     */
    #[Test]
    public function responds_with_429_once_the_limit_is_exceeded(): void
    {
        for ($i = 0; $i < self::LIMIT; $i++) {
            ($this->listener)($this->requestEvent());
        }

        try {
            ($this->listener)($this->requestEvent());
            $this->fail('The request over the limit was not rejected.');
        } catch (TooManyRequestsHttpException $e) {
            $this->assertSame(429, $e->getStatusCode());
        }
    }

    protected function requestEvent(): RequestEvent
    {
        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/excursions'),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
