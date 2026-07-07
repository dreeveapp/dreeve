<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Settings\SettingsRepository;
use Carbon\Carbon;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

final readonly class AppLocaleRequestListener implements EventSubscriberInterface
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $locale = $this->settingsRepository->appearance()->getLocale();
        $event->getRequest()->setLocale($locale->value);
        $this->localeSwitcher->setLocale($locale->value);
        Carbon::setLocale($locale->value);
    }

    /**
     * @codeCoverageIgnore
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => [['onKernelRequest', 14]]];
    }
}
