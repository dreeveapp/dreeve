<?php

declare(strict_types=1);

namespace App\Tests\Domain\Integration\Notification\Shoutrrr;

use App\Domain\Integration\Notification\Shoutrrr\Shoutrrr;
use App\Domain\Integration\Notification\Shoutrrr\ShoutrrrUrl;

class SpyShoutrrr implements Shoutrrr
{
    /** @var list<array{url: ShoutrrrUrl, message: string, title: string}> */
    private array $notifications = [];

    public function send(ShoutrrrUrl $shoutrrrUrl, string $message, string $title): void
    {
        $this->notifications[] = [
            'url' => $shoutrrrUrl,
            'message' => $message,
            'title' => $title,
        ];
    }

    /**
     * @return list<array{url: ShoutrrrUrl, message: string, title: string}>
     */
    public function getNotifications(): array
    {
        return $this->notifications;
    }
}
