<?php

declare(strict_types=1);

namespace App\Tests;

use App\Application\AppVersion;
use Spatie\Snapshots\Drivers\HtmlDriver;
use Spatie\Snapshots\MatchesSnapshots;

trait MatchesVersionlessSnapshots
{
    use MatchesSnapshots;

    public function assertMatchesHtmlSnapshot(string $actual, ?string $id = null): void
    {
        $this->assertMatchesSnapshot(
            actual: str_replace(AppVersion::getSemanticVersion(), '[APP_VERSION]', $actual),
            driver: new HtmlDriver(),
            id: $id,
        );
    }
}
