<?php

namespace App\EventSubscriber;

use App\Exception\ApiException;
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

    /**
     * Replaces the response with a problem details response when the exception is a domain exception
     * with an HTTP status. Headers declared in #[WithHttpStatus] (e.g. Retry-After) are sent as well.
     *
     * @param ExceptionEvent $event Exception event of the current request.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $this->unwrap($event->getThrowable());
        $httpStatus = $this->findHttpStatus($exception);

        if (!$exception instanceof ApiException || is_null($httpStatus)) {
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
     * Returns the original exception when Symfony's ErrorListener has wrapped it.
     *
     * The ErrorListener wraps exceptions carrying #[WithHttpStatus] in an HttpException and keeps
     * the original one as "previous".
     *
     * @param Throwable $exception Exception of the event, possibly wrapped.
     *
     * @return Throwable The original exception, or the given one if it is not wrapped.
     */
    protected function unwrap(Throwable $exception): Throwable
    {
        if ($exception instanceof HttpExceptionInterface && $exception->getPrevious() !== null) {
            return $exception->getPrevious();
        }

        return $exception;
    }

    /**
     * Finds the #[WithHttpStatus] attribute on the exception class or on its closest parent declaring it.
     *
     * @param Throwable $exception Exception to inspect.
     *
     * @return WithHttpStatus|null The attribute, or null when no class in the hierarchy declares it.
     */
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
