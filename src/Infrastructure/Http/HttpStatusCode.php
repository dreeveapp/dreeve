<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

enum HttpStatusCode: int
{
    case NOT_FOUND = 404;
    case UNAUTHORIZED = 401;
    case BAD_REQUEST = 400;
    case METHOD_NOT_ALLOWED = 405;
    case INTERNAL_SERVER_ERROR = 500;
    case TOO_MANY_REQUESTS = 429;
}
