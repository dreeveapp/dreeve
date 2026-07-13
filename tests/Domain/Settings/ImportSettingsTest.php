<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Activity\ActivityVisibility;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Settings\ImportSettings;
use App\Domain\Strava\Webhook\InvalidWebhookConfig;
use PHPUnit\Framework\TestCase;

class ImportSettingsTest extends TestCase
{
    public function testItAppliesDefaultsForAnEmptyConfiguration(): void
    {
        $settings = ImportSettings::fromArray([]);

        // Empty list means "import all".
        $this->assertCount(count(SportType::cases()), $settings->getSportTypesToImport());
        $this->assertCount(count(ActivityVisibility::cases()), $settings->getActivityVisibilitiesToImport());
        $this->assertCount(0, $settings->getActivitiesToSkipDuringImport());
        $this->assertNull($settings->getSkipActivitiesRecordedBefore());
        $this->assertFalse($settings->getOptInToSegmentDetailsImport()->hasOptedIn());
        $this->assertFalse($settings->getWebhookConfig()->isEnabled());
    }

    public function testItBuildsFromStoredValues(): void
    {
        $settings = ImportSettings::fromArray([
            'numberOfNewActivitiesToProcessPerImport' => 10,
            'sportTypesToImport' => ['Ride'],
            'activityVisibilitiesToImport' => ['everyone'],
            'skipActivitiesRecordedBefore' => '2023-09-01',
            'activitiesToSkipDuringImport' => ['123', '456'],
            'optInToSegmentDetailImport' => true,
            'webhooks' => [
                'enabled' => true,
                'verifyToken' => 'el-token',
                'checkIntervalInMinutes' => 5,
            ],
        ]);

        $this->assertTrue($settings->getSportTypesToImport()->has(SportType::RIDE));
        $this->assertCount(1, $settings->getSportTypesToImport());
        $this->assertTrue($settings->getActivityVisibilitiesToImport()->has(ActivityVisibility::EVERYONE));
        $this->assertCount(1, $settings->getActivityVisibilitiesToImport());
        $this->assertCount(2, $settings->getActivitiesToSkipDuringImport());
        $this->assertSame('2023-09-01', $settings->getSkipActivitiesRecordedBefore()?->format('Y-m-d'));
        $this->assertTrue($settings->getOptInToSegmentDetailsImport()->hasOptedIn());
        $this->assertTrue($settings->getWebhookConfig()->isEnabled());
        $this->assertSame('el-token', $settings->getWebhookConfig()->getVerifyToken());
        $this->assertSame('*/5 * * * *', (string) $settings->getWebhookConfig()->getCronExpression());
    }

    public function testItThrowsForAnInvalidWebhookConfig(): void
    {
        $this->expectExceptionObject(new InvalidWebhookConfig('"verifyToken" property cannot be empty.'));

        ImportSettings::fromArray(['webhooks' => ['enabled' => true]]);
    }

    public function testItThrowsForAnInvalidSportType(): void
    {
        $this->expectExceptionObject(new \ValueError('"NotASportType" is not a valid backing value for enum App\\Domain\\Activity\\SportType\\SportType'));

        ImportSettings::fromArray(['sportTypesToImport' => ['NotASportType']]);
    }
}
