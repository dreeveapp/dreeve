<?php

namespace App\Tests\Controller\Page;

use App\Domain\Import\ImportMode;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\SegmentType;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Segment\SegmentBuilder;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;

class AdminLinkTest extends AdminWebTestCase
{
    use ProvideTestData;

    #[DataProvider('providePages')]
    public function testItOnlyRendersTheAdminLinkForAuthenticatedVisitors(string $url, string $adminPath, string $adminLink): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', $url);
        $this->assertStringNotContainsString($adminPath, (string) $this->client->getResponse()->getContent());

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', $url);
        $this->assertStringContainsString($adminLink, (string) $this->client->getResponse()->getContent());
    }

    public function testItOnlyRendersTheSegmentAdminLinkInFileImportMode(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->provideFullTestSet();

        $this->client->request('GET', '/segments');
        $this->assertStringNotContainsString('admin/segments', (string) $this->client->getResponse()->getContent());

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/segments');
        $this->assertStringContainsString('admin/segments?redirectTo=%2Fsegments', (string) $this->client->getResponse()->getContent());

        $this->withImportMode(ImportMode::STRAVA_API);
        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/segments');
        $this->assertStringNotContainsString('admin/segments', (string) $this->client->getResponse()->getContent());
    }

    public function testItOnlyRendersTheSegmentEditLinkForCustomSegmentsInFileImportMode(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->provideFullTestSet();
        foreach ([['custom', SegmentType::CUSTOM], ['imported', SegmentType::IMPORTED]] as [$id, $type]) {
            $this->getContainer()->get(SegmentRepository::class)->add(SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed($id))
                ->withType($type)
                ->build());
        }

        $this->client->request('GET', '/segments/segment-custom');
        $this->assertStringNotContainsString('admin/segments/segment-custom/edit', (string) $this->client->getResponse()->getContent());

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/segments/segment-custom');
        $this->assertStringContainsString('admin/segments/segment-custom/edit?redirectTo=%2Fsegments%2Fsegment-custom', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/segments/segment-imported');
        $this->assertStringNotContainsString('admin/segments/segment-imported/edit', (string) $this->client->getResponse()->getContent());

        $this->withImportMode(ImportMode::STRAVA_API);
        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/segments/segment-custom');
        $this->assertStringNotContainsString('admin/segments/segment-custom/edit', (string) $this->client->getResponse()->getContent());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providePages(): iterable
    {
        yield 'activities' => ['/activities', 'admin/activities', 'admin/activities?redirectTo=%2Factivities'];
        yield 'activity' => ['/activities/activity-9756441741', 'admin/activities/activity-9756441741/edit', 'admin/activities/activity-9756441741/edit?redirectTo=%2Factivities%2Factivity-9756441741'];
        yield 'dashboard' => ['/dashboard', 'admin/settings/dashboard', 'admin/settings/dashboard?redirectTo=%2Fdashboard'];
        yield 'power output' => ['/dashboard/power-output', 'admin/settings/metrics', 'admin/settings/metrics?redirectTo=%2Fdashboard%2Fpower-output'];
        yield 'eddington' => ['/eddington', 'admin/settings/metrics', 'admin/settings/metrics?redirectTo=%2Feddington'];
        yield 'heatmap' => ['/heatmap', 'admin/settings/maps', 'admin/settings/maps?redirectTo=%2Fheatmap'];
        yield 'gear' => ['/gear', 'admin/gear', 'admin/gear?redirectTo=%2Fgear'];
        yield 'gear maintenance' => ['/gear/maintenance', 'admin/gear/maintenance-config', 'admin/gear/maintenance-config?redirectTo=%2Fgear%2Fmaintenance'];
        yield 'recording devices' => ['/gear/recording-devices', 'admin/gear/recording-devices', 'admin/gear/recording-devices?redirectTo=%2Fgear%2Frecording-devices'];
    }
}
