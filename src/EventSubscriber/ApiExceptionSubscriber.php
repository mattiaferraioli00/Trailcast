<?php

namespace App\EventSubscriber;

use App\Exceptions\TException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ApiExceptionSubscriber implements EventSubscriberInterface
{

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', -64]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $this->unwrap($event->getThrowable());
        $httpStatus = $this->findHttpStatus($exception);

        if (!$exception instanceof TException ||is_null($httpStatus)) {
            return;
        }

        $body = [
            'title' => Response::$statusTexts[$httpStatus->statusCode] ?? 'Error',
            'status' => $httpStatus->statusCode,
            'detail' => $exception->getMessage(),
            'params' => $exception->getParams(),
        ];

        $event->setResponse(new JsonResponse(
            $body,
            $httpStatus->statusCode,
            $httpStatus->headers + ['Content-Type' => 'application/problem+json']
        ));
    }

    /**
     * Symfony's ErrorListener wraps exceptions carrying #[WithHttpStatus]
     * in an HttpException and keeps the original one as "previous".
     */
    protected function unwrap(Throwable $exception): Throwable
    {
        if ($exception instanceof HttpExceptionInterface && $exception->getPrevious() !== null) {
            return $exception->getPrevious();
        }

        return $exception;
    }

    protected function findHttpStatus(Throwable $exception): ?WithHttpStatus
    {
        $class = new \ReflectionClass($exception);

        do {
            $attributes = $class->getAttributes(WithHttpStatus::class);

            if ($attributes !== []) {
                /** @var WithHttpStatus $httpStatus */
                $httpStatus = $attributes[0]->newInstance();

                return $httpStatus;
            }
        } while ($class = $class->getParentClass());

        return null;
    }
}
