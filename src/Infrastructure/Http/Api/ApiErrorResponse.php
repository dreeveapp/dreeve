<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiErrorResponse extends JsonResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(int $statusCode, ApiErrorCode $error, string $message, array $headers = [])
    {
        parent::__construct(
            data: [
                'error' => $error->value,
                'message' => $message,
            ],
            status: $statusCode,
            headers: $headers,
        );
    }
}
