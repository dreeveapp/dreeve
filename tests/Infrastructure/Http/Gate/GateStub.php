<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http\Gate;

use App\Infrastructure\Http\Gate\Gate;
use App\Infrastructure\Http\Gate\GateDecision;
use Symfony\Component\HttpFoundation\Request;

final readonly class GateStub implements Gate
{
    /**
     * @param \ArrayObject<int, string>|null $calls
     */
    public function __construct(
        private GateDecision $decision,
        private ?\ArrayObject $calls = null,
        private string $name = '',
    ) {
    }

    public function handle(Request $request): GateDecision
    {
        $this->calls?->append($this->name);

        return $this->decision;
    }
}
