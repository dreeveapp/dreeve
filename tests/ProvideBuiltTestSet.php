<?php

declare(strict_types=1);

namespace App\Tests;

trait ProvideBuiltTestSet
{
    use ProvideTestData;

    protected function provideBuiltTestSet(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();
    }

    protected function shouldMarkAppAsBuilt(): bool
    {
        return false;
    }
}
