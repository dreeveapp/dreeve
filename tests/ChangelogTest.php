<?php

declare(strict_types=1);

namespace App\Tests;

use App\Application\AppVersion;
use PHPUnit\Framework\TestCase;

class ChangelogTest extends TestCase
{
    public function testThatChangelogContainsLatestVersion(): void
    {
        $changelog = (string) file_get_contents(__DIR__.'/../CHANGELOG.md');

        $latestVersion = AppVersion::getSemanticVersion();
        $this->assertStringContainsString(
            sprintf('# [%s](https://github.com/dreeveapp/dreeve/releases/tag/%s', $latestVersion, $latestVersion),
            $changelog
        );
    }
}
