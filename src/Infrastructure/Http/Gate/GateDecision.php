<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Gate;

use Symfony\Component\HttpFoundation\Response;

final readonly class GateDecision
{
    private function __construct(
        private bool $hasBeenApplied,
        private ?Response $response,
    ) {
    }

    /**
     * The gate has no say in this request, the next gate gets to decide.
     */
    public static function defer(): self
    {
        return new self(
            hasBeenApplied: false,
            response: null
        );
    }

    public static function allow(): self
    {
        return new self(
            hasBeenApplied: true,
            response: null
        );
    }

    public static function respond(Response $response): self
    {
        return new self(
            hasBeenApplied: true,
            response: $response
        );
    }

    public function hasBeenApplied(): bool
    {
        return $this->hasBeenApplied;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }
}
