<?php

namespace App\Tests\Unit\Listeners;

use App\Attributes\ResponseStatus;
use App\Listeners\KernelViewJsonResponseListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[CoversClass(KernelViewJsonResponseListener::class)]
class KernelViewJsonResponseListenerTest extends TestCase
{
    protected KernelViewJsonResponseListener $listener;

    protected object $controller;

    protected string $json;

    /**
     * Loads the JSON fixture and builds the listener with a serializer stub returning it,
     * plus a controller double with a plain method and one annotated with #[ResponseStatus(201)].
     */
    protected function setUp(): void
    {
        $this->json = file_get_contents(__DIR__ . '/../../Fixtures/listeners/excursion.json');

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('serialize')->willReturn($this->json);

        $this->listener = new KernelViewJsonResponseListener($serializer);

        $this->controller = new class {
            public function plain(): void {}

            #[ResponseStatus(201)]
            public function created(): void {}
        };
    }

    /**
     * Given a controller method without #[ResponseStatus],
     * when its result is turned into a response,
     * then the status code is 200.
     */
    #[Test]
    public function responds_with_200_when_the_controller_has_no_response_status(): void
    {
        $event = $this->viewEvent([$this->controller, 'plain']);

        ($this->listener)($event);

        $this->assertSame(200, $event->getResponse()->getStatusCode());
    }

    /**
     * Given a controller method annotated with #[ResponseStatus(201)],
     * when its result is turned into a response,
     * then the status code is the one of the attribute.
     */
    #[Test]
    public function uses_the_status_code_of_the_response_status_attribute(): void
    {
        $event = $this->viewEvent([$this->controller, 'created']);

        ($this->listener)($event);

        $this->assertSame(201, $event->getResponse()->getStatusCode());
    }

    /**
     * Given a value returned by a controller,
     * when it is turned into a response,
     * then the response is a JSON containing the serialized value.
     */
    #[Test]
    public function responds_with_the_serialized_result_as_json(): void
    {
        $event = $this->viewEvent([$this->controller, 'plain']);

        ($this->listener)($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame($this->json, $response->getContent());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    /**
     * Given a view event without the controller arguments event,
     * when its result is turned into a response,
     * then the status code is 200.
     */
    #[Test]
    public function defaults_to_200_without_the_controller_arguments_event(): void
    {
        $event = $this->viewEvent();

        ($this->listener)($event);

        $this->assertSame(200, $event->getResponse()->getStatusCode());
    }

    /**
     * Builds the kernel.view event for a controller result.
     *
     * @param callable|null $controller Controller of the request; when null the event carries no ControllerArgumentsEvent.
     *
     * @return ViewEvent The event to hand to the listener.
     */
    protected function viewEvent(?callable $controller = null): ViewEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/excursions');

        $argumentsEvent = is_null($controller)
            ? null
            : new ControllerArgumentsEvent($kernel, $controller, [], $request, HttpKernelInterface::MAIN_REQUEST);

        return new ViewEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new \stdClass(),
            $argumentsEvent,
        );
    }
}
