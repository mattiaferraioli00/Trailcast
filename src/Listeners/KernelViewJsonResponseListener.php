<?php

namespace App\Listeners;

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
        $json = $this->serializer->serialize(
            $event -> getControllerResult (),
            'json' ,
        );

        $event->setResponse(new JsonResponse($json , json : true));
    }
}
