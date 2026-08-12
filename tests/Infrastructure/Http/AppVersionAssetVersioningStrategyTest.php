<?php

namespace App\Tests\Infrastructure\Http;

use App\Application\AppVersion;
use App\Infrastructure\Http\AppVersionAssetVersioningStrategy;
use PHPUnit\Framework\TestCase;

class AppVersionAssetVersioningStrategyTest extends TestCase
{
    public function testApplyVersion(): void
    {
        $strategy = new AppVersionAssetVersioningStrategy();
        $version = AppVersion::getSemanticVersion();

        $this->assertEquals(
            '/test/file?'.$version,
            $strategy->applyVersion('/test/file')
        );

        $this->assertEquals(
            'test/file?'.$version,
            $strategy->applyVersion('test/file')
        );
    }

    public function testItShouldBeStableAcrossInstances(): void
    {
        $this->assertEquals(
            new AppVersionAssetVersioningStrategy()->applyVersion('/test/file'),
            new AppVersionAssetVersioningStrategy()->applyVersion('/test/file')
        );
    }
}
