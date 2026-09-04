<?php

declare(strict_types=1);

namespace App\Infrastructure\CQRS\Command;

use Symfony\Contracts\Translation\TranslatorInterface;

interface ProvidesFlashMessage
{
    public function getFlashMessage(TranslatorInterface $translator): string;
}
