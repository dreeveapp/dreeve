<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Gate;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class GateRequestListener implements EventSubscriberInterface
{
    /**
     * @param iterable<Gate> $gates
     */
    public function __construct(
        #[AutowireIterator('app.http.gate')]
        private iterable $gates,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($this->isAlwaysOpen($request->getPathInfo())) {
            return;
        }

        foreach ($this->gates as $gate) {
            $decision = $gate->handle($request);
            if (!$decision->hasBeenApplied()) {
                continue;
            }

            if ($response = $decision->getResponse()) {
                $event->setResponse($response);
            }

            return;
        }
    }

    private function isAlwaysOpen(string $path): bool
    {
        return 1 === preg_match('#^/(_(profiler|wdt)|css|images|js)/#', $path)
            || '/strava/webhook' === $path;
    }

    /**
     * @codeCoverageIgnore
     */
    public static function getSubscribedEvents(): array
    {
        // Priority 12: after the RouterListener, before the firewall (priority 8)
        // so a gate can preempt the login redirect.
        return [KernelEvents::REQUEST => [['onKernelRequest', 12]]];
    }
}
