<?php

namespace App\Listeners;

use App\Attributes\ResponseStatus;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[AsEventListener(KernelEvents::VIEW)]
class KernelViewJsonResponseListener
{
    public function __construct(
        private SerializerInterface $serializer,
    ){}


    /**
     * @throws ExceptionInterface
     */
    public function __invoke(ViewEvent $event): void
    {
        $attributes = $event->controllerArgumentsEvent?->getAttributes(ResponseStatus::class) ?? [];
        $status = ($attributes[0] ?? null)?->code;

        $json = $this->serializer->serialize(
            $event -> getControllerResult (),
            'json' ,
        );

        $event->setResponse(
            new JsonResponse(
                data: $json,
                status: $status,
                json: true
            )
        );
    }
}
