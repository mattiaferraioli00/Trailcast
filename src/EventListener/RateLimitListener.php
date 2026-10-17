<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

#[AsEventListener(KernelEvents::REQUEST, priority: 16)]
readonly class RateLimitListener
{
    private RateLimiterFactoryInterface $apiLimiter;

    /**
     * @param RateLimiterFactoryInterface $apiLimiter The "api" limiter configured in config/packages/rate_limiter.yaml,
     *                                                autowired by argument name.
     */
    public function __construct(RateLimiterFactoryInterface $apiLimiter)
    {
        $this->apiLimiter = $apiLimiter;
    }

    /**
     * Limits every request per client IP, whatever the route.
     *
     * Only Symfony internal routes (name starting with "_": profiler, web debug toolbar, error previews)
     * are skipped. Requests that match no route never get here: the router has already thrown a 404.
     *
     * @throws TooManyRequestsHttpException When the client IP has used up its requests for the current window (429).
     */
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');

        if (!$event->isMainRequest() || str_starts_with($route, '_')) {
            return;
        }

        $limit = $this->apiLimiter->create($request->getClientIp())->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                max(1, $limit->getRetryAfter()->getTimestamp() - time()),
                'Too many requests. Please try again later.',
            );
        }
    }
}
