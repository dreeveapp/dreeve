<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Admin;

use App\Infrastructure\ValueObject\String\NonEmptyStringLiteral;

final readonly class AdminUserName extends NonEmptyStringLiteral
{
}
