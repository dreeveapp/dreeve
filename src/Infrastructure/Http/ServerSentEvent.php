<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Symfony\Component\HttpFoundation\ServerEvent;

final class ServerSentEvent extends ServerEvent
{
    public function __construct(
        string $data,
        ?string $type = null,
        ?int $retry = null,
        ?string $id = null,
        ?string $comment = null,
    ) {
        parent::__construct(
            data: str_replace("\n", '\\n', $data),
            type: $type,
            retry: $retry,
            id: $id,
            comment: $comment
        );
    }
}
