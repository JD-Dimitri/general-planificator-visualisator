<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Toute erreur sous /api ressort en JSON, jamais en page HTML :
 * le front n'a qu'un seul format à lire.
 */
#[AsEventListener(event: 'kernel.exception', priority: 0)]
final class ApiExceptionListener
{
    public function __construct(private readonly bool $debug = false)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof AccessDeniedException) {
            $event->setResponse(new JsonResponse([
                'error' => 'forbidden',
                'message' => "Cet élément n'appartient pas à ton compte.",
            ], Response::HTTP_FORBIDDEN));

            return;
        }

        $status = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        $payload = [
            'error' => Response::HTTP_INTERNAL_SERVER_ERROR === $status ? 'server_error' : 'request_error',
            'message' => Response::HTTP_INTERNAL_SERVER_ERROR === $status && !$this->debug
                ? "Une erreur est survenue. Réessaie dans quelques instants."
                : $exception->getMessage(),
        ];

        if ($this->debug && Response::HTTP_INTERNAL_SERVER_ERROR === $status) {
            $payload['exception'] = $exception::class;
            $payload['trace'] = array_slice(explode("\n", $exception->getTraceAsString()), 0, 10);
        }

        $event->setResponse(new JsonResponse($payload, $status));
    }
}
