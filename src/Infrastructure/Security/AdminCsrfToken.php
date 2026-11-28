<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;

final class AdminCsrfToken
{
    public const string ID = 'admin';
    private const string HEADER = 'X-CSRF-TOKEN';

    public static function fromRequest(Request $request): CsrfToken
    {
        return new CsrfToken(self::ID, $request->headers->get(self::HEADER, ''));
    }
}
