<?php

namespace App\Listeners;

use App\Attributes\ResponseStatus;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[AsEventListener(KernelEvents::VIEW)]
readonly class KernelViewJsonResponseListener
{
    public function __construct(
        protected SerializerInterface $serializer,
    ){}


    /**
     * Turns the value returned by a controller into a JSON response.
     *
     * Controllers return view objects (src/Http/Resources) instead of Response objects. The value is
     * serialized with the Symfony Serializer, and the status code comes from the #[ResponseStatus]
     * attribute of the controller method, 200 when the attribute is missing.
     *
     * Exceptions never reach this listener: they are handled on kernel.exception.
     * @throws ExceptionInterface
     */
    public function __invoke(ViewEvent $event): void
    {
        $attributes = $event->controllerArgumentsEvent?->getAttributes(ResponseStatus::class) ?? [];

        if (!empty($attributes)) {
            $status = $attributes[0]->code;
        }

        $json = $this->serializer->serialize($event->getControllerResult(), 'json');

        $event->setResponse(
            new JsonResponse(
                data: $json,
                status: $status ?? Response::HTTP_OK,
                json: true
            )
        );
    }
}
