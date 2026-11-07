<?php

declare(strict_types=1);

namespace App\Infrastructure\CQRS\Command;

use Symfony\Contracts\Translation\TranslatableInterface;

interface ProvidesFlashMessage
{
    public function getFlashMessage(): TranslatableInterface;
}
