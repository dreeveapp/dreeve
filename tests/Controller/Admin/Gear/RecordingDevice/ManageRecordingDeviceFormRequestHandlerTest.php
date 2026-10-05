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

class ManageRecordingDeviceFormRequestHandlerTest extends AdminWebTestCase
{
    public function testRendersTheEditFormPrefilledWithTheRecordingDevice(): void
    {
        static::getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withDeviceName('Garmin Edge 530')
                ->build(),
            []
        ));
        static::getContainer()->get(RecordingDeviceRepository::class)->save(
            RecordingDevice::create(
                name: 'Garmin Edge 530',
                purchasePrice: Money::EUR(29950),
            )
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/recording-devices/'.RecordingDeviceId::fromName('Garmin Edge 530').'/edit');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Edit recording device', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="update-recording-device"]');
        $this->assertCount(1, $form);

        // The name is not editable; it is mirrored into a hidden input and shown disabled.
        $this->assertSame('Garmin Edge 530', $form->filter('input[type="hidden"][name="name"]')->attr('value'));
        $this->assertNotNull($crawler->filter('input#recording-device-name')->attr('disabled'));

        // The purchase price is editable and prefilled.
        $this->assertSame('299.50', $form->filter('input[name="purchasePriceAmount"]')->attr('value'));
        $this->assertSame('EUR', $form->filter('select[name="purchasePriceCurrency"] option[selected]')->attr('value'));
    }
}
