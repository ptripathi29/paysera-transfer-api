<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\RequestContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RequestContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestContext $requestContext,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 100],
            KernelEvents::RESPONSE => ['onResponse', -10],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $correlationId = $request->headers->get('X-Correlation-Id')
            ?? $request->headers->get('X-Request-Id');

        $this->requestContext->setCorrelationId($correlationId);

        $this->logger->info('Incoming request', [
            'request_id' => $this->requestContext->getRequestId(),
            'correlation_id' => $this->requestContext->getCorrelationId(),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
        ]);
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->set('X-Request-Id', $this->requestContext->getRequestId());
        $event->getResponse()->headers->set('X-Correlation-Id', $this->requestContext->getCorrelationId());
    }
}
