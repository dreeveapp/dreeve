<?php

declare(strict_types=1);

namespace App\Tests;

/**
 * Fragments are only reachable once AppHasBeenBuiltGate is satisfied, which needs both a build
 * snapshot and at least one activity. Marking the app as built seeds a placeholder activity when
 * the database is still empty, so the fixtures have to land first to keep it out of the snapshots.
 */
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
