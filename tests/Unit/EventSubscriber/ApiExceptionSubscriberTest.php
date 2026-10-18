<?php

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\ApiExceptionSubscriber;
use App\Exception\NotEnoughSeatsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(ApiExceptionSubscriber::class)]
class ApiExceptionSubscriberTest extends TestCase
{
    /**
     * Given a domain exception declaring its HTTP status,
     * when it reaches the subscriber,
     * then it is turned into a problem details response with that status, the message and the params.
     */
    #[Test]
    public function turns_a_domain_exception_into_a_problem_details_response(): void
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/excursions/1/bookings', 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
            new NotEnoughSeatsException(message: 'Not enough seats.', seats: 3),
        );

        (new ApiExceptionSubscriber())->onKernelException($event);

        $response = $event->getResponse();
        $body = json_decode($response->getContent(), true);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $this->assertSame(409, $body['status']);
        $this->assertSame('Not enough seats.', $body['detail']);
        $this->assertSame(['seats' => 3], $body['params']);
    }
}
