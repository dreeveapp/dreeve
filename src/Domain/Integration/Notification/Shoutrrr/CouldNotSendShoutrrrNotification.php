<?php

namespace App\Domain\Integration\Notification\Shoutrrr;

final class CouldNotSendShoutrrrNotification extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $message = trim((string) preg_replace('/\s\s+/', ' ', $message));
        parent::__construct($message, $code, $previous);
    }
}
