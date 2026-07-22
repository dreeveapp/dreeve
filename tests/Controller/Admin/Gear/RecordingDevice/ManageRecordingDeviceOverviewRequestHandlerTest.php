<?php

namespace App\Tests\Controller\Admin\Gear\RecordingDevice;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\RecordingDevice\RecordingDevice;
use App\Domain\Gear\RecordingDevice\RecordingDeviceId;
use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Money\Money;

class ManageRecordingDeviceOverviewRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/gear/recording-devices');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testRendersTheEmptyStateWhenThereAreNoRecordingDevices(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/recording-devices');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('table.data-table'));
        $this->assertStringContainsString('No recording devices yet.', $crawler->filter('body')->text());
        $this->assertCount(1, $crawler->filter('table.data-table tbody td[colspan="3"]'));
        $this->assertCount(0, $crawler->filter('table.data-table tbody a[title="Edit"]'));
    }

    public function testRendersTheTableWithRecordingDevices(): void
    {
        $activityRepository = static::getContainer()->get(ActivityRepository::class);
        foreach (['Garmin Edge 530', 'Garmin Edge 530', 'Wahoo ELEMNT'] as $index => $deviceName) {
            $activityRepository->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed((string) ($index + 1)))
                    ->withDeviceName($deviceName)
                    ->build(),
                []
            ));
        }
        static::getContainer()->get(RecordingDeviceRepository::class)->save(
            RecordingDevice::create(
                name: 'Garmin Edge 530',
                purchasePrice: Money::EUR(29950),
            )
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/recording-devices');

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $crawler->filter('table.data-table tbody tr'));
        $this->assertStringNotContainsString('No recording devices yet.', $crawler->filter('body')->text());

        // Devices are ordered by activity count, so the Garmin comes first.
        $firstRow = $crawler->filter('table.data-table tbody tr')->first();
        $this->assertStringContainsString('Garmin Edge 530', $firstRow->text());
        $this->assertStringContainsString('299.50', $firstRow->text());

        // The Wahoo has no persisted purchase price.
        $lastRow = $crawler->filter('table.data-table tbody tr')->last();
        $this->assertStringContainsString('Wahoo ELEMNT', $lastRow->text());
        $this->assertStringContainsString('-', $lastRow->filter('td')->eq(1)->text());

        $editLinks = $crawler->filter('table.data-table tbody a[title="Edit"]');
        $this->assertCount(2, $editLinks);
        $this->assertStringContainsString(
            '/admin/gear/recording-devices/'.RecordingDeviceId::fromName('Garmin Edge 530').'/edit',
            $editLinks->first()->attr('href')
        );
    }
}
