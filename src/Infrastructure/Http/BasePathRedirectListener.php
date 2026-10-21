<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\AppUrl;
use App\Infrastructure\ValueObject\String\RelativeUrl;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * When Dreeve is served from a sub-path (APP_URL=https://example.com/dreeve), the reverse proxy strips
 * that prefix before the request reaches the app. Routing, controllers and Symfony's security component
 * therefore only know root-relative paths, and so do the redirects they create.
 *
 * Instead of teaching every redirect about the base path, this listener adds it in one place: any redirect
 * pointing to this app (a root-relative path or an absolute URL on the current origin) is prefixed.
 * Redirects to other origins are left untouched.
 *
 * Contract: code that creates a redirect must NOT add the base path itself.
 */
final readonly class BasePathRedirectListener implements EventSubscriberInterface
{
    public function __construct(
        private AppUrl $appUrl,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || null === $this->appUrl->getBasePath()) {
            return;
        }

        $response = $event->getResponse();
        $location = $response->headers->get('Location');
        if (!$response->isRedirection() || !is_string($location) || '' === $location) {
            return;
        }

        $prefixed = $this->prefix($location, $event->getRequest()->getSchemeAndHttpHost());
        if ($prefixed === $location) {
            return;
        }

        if ($response instanceof RedirectResponse) {
            $response->setTargetUrl($prefixed);

            return;
        }

        $response->headers->set('Location', $prefixed);
    }

    private function prefix(string $location, string $origin): string
    {
        if (str_starts_with($location, '//')) {
            return $location;
        }

        if (str_starts_with($location, '/')) {
            return RelativeUrl::from($location, $this->appUrl)->toRelativeUrl();
        }

        if ($location === $origin || str_starts_with($location, $origin.'/')) {
            return $origin.RelativeUrl::from(substr($location, strlen($origin)), $this->appUrl)->toRelativeUrl();
        }

        return $location;
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onKernelResponse']];
    }
}
