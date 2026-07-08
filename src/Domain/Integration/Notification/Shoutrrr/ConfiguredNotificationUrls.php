<?php

namespace App\Domain\Integration\Notification\Shoutrrr;

final readonly class ConfiguredNotificationUrls implements \IteratorAggregate
{
    public function __construct(
        /** @var ShoutrrrUrl[] */
        private array $shoutrrrUrls,
    ) {
    }

    /**
     * @param array<mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        $configuredNotificationUrls = [];
        foreach ($config as $notificationService) {
            if (!is_string($notificationService)) {
                throw new \RuntimeException('Notification service name must be a string');
            }
            $configuredNotificationUrls[] = ShoutrrrUrl::fromString($notificationService);
        }

        return new self($configuredNotificationUrls);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->shoutrrrUrls);
    }
}
