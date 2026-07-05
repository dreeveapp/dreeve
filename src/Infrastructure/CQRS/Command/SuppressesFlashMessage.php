<?php

declare(strict_types=1);

namespace App\Infrastructure\CQRS\Command;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class SuppressesFlashMessage
{
}
