<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\TransferException;
use App\Service\RequestContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly RequestContext $requestContext,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onException'];
    }

    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof TransferException) {
            $event->setResponse(new JsonResponse([
                'error' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ], $exception->getHttpStatus(), [
                'X-Request-Id' => $this->requestContext->getRequestId(),
            ]));

            return;
        }

        $status = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;

        $this->logger->error('Unhandled exception', [
            'request_id' => $this->requestContext->getRequestId(),
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        $message = $status >= 500
            ? 'An internal error occurred.'
            : $exception->getMessage();

        $event->setResponse(new JsonResponse([
            'error' => $status >= 500 ? 'INTERNAL_ERROR' : 'REQUEST_ERROR',
            'message' => $message,
        ], $status, [
            'X-Request-Id' => $this->requestContext->getRequestId(),
        ]));
    }
}
