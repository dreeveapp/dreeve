<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Cache;

final class RenderStub
{
    public int $renderCount = 0;
    public ?string $rendered = 'rendered';

    public function __invoke(): ?string
    {
        ++$this->renderCount;

        return $this->rendered;
    }
}
