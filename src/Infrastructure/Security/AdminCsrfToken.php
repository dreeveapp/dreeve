<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

final class AdminCsrfToken
{
    public const string ID = 'admin';
    public const string HEADER = 'X-CSRF-TOKEN';
}
