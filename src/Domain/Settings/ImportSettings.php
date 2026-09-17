<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Application\Import\StravaImport\ImportActivities\ActivitiesToSkipDuringImport;
use App\Application\Import\StravaImport\ImportActivities\ActivityVisibilitiesToImport;
use App\Application\Import\StravaImport\ImportActivities\NumberOfNewActivitiesToProcessPerImport;
use App\Application\Import\StravaImport\ImportActivities\SkipActivitiesRecordedBefore;
use App\Domain\Activity\SportType\SportTypesToImport;
use App\Domain\Strava\Webhook\WebhookConfig;

final readonly class ImportSettings
{
    private function __construct(
        private NumberOfNewActivitiesToProcessPerImport $numberOfNewActivitiesToProcessPerImport,
        private SportTypesToImport $sportTypesToImport,
        private ActivityVisibilitiesToImport $activityVisibilitiesToImport,
        private ActivitiesToSkipDuringImport $activitiesToSkipDuringImport,
        private ?SkipActivitiesRecordedBefore $skipActivitiesRecordedBefore,
        private bool $importSegmentDetails,
        private bool $skipImageDownloadDuringImport,
        private WebhookConfig $webhookConfig,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        $activitiesToSkip = $data['activitiesToSkipDuringImport'] ?? [];
        $activitiesToSkip = array_values(array_filter(
            $activitiesToSkip,
            static fn (mixed $id): bool => '' !== trim((string) $id)
        ));

        $webhooks = $data['webhooks'] ?? [];
        if (array_key_exists('enabled', $webhooks)) {
            $webhooks['enabled'] = filter_var($webhooks['enabled'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('checkIntervalInMinutes', $webhooks) && is_numeric($webhooks['checkIntervalInMinutes'])) {
            $webhooks['checkIntervalInMinutes'] = (int) $webhooks['checkIntervalInMinutes'];
        }

        return new self(
            numberOfNewActivitiesToProcessPerImport: NumberOfNewActivitiesToProcessPerImport::fromInt((int) ($data['numberOfNewActivitiesToProcessPerImport'] ?? 250)),
            sportTypesToImport: SportTypesToImport::from($data['sportTypesToImport'] ?? []),
            activityVisibilitiesToImport: ActivityVisibilitiesToImport::from($data['activityVisibilitiesToImport'] ?? []),
            activitiesToSkipDuringImport: ActivitiesToSkipDuringImport::from($activitiesToSkip),
            skipActivitiesRecordedBefore: SkipActivitiesRecordedBefore::fromOptionalString($data['skipActivitiesRecordedBefore'] ?? null),
            importSegmentDetails: filter_var($data['optInToSegmentDetailImport'] ?? false, FILTER_VALIDATE_BOOLEAN),
            skipImageDownloadDuringImport: filter_var($data['skipImageDownloadDuringImport'] ?? false, FILTER_VALIDATE_BOOLEAN),
            webhookConfig: WebhookConfig::fromArray($webhooks),
        );
    }

    public function getNumberOfNewActivitiesToProcessPerImport(): NumberOfNewActivitiesToProcessPerImport
    {
        return $this->numberOfNewActivitiesToProcessPerImport;
    }

    public function getSportTypesToImport(): SportTypesToImport
    {
        return $this->sportTypesToImport;
    }

    public function getActivityVisibilitiesToImport(): ActivityVisibilitiesToImport
    {
        return $this->activityVisibilitiesToImport;
    }

    public function getActivitiesToSkipDuringImport(): ActivitiesToSkipDuringImport
    {
        return $this->activitiesToSkipDuringImport;
    }

    public function getSkipActivitiesRecordedBefore(): ?SkipActivitiesRecordedBefore
    {
        return $this->skipActivitiesRecordedBefore;
    }

    public function shouldImportSegmentDetails(): bool
    {
        return $this->importSegmentDetails;
    }

    public function shouldSkipImageDownloadDuringImport(): bool
    {
        return $this->skipImageDownloadDuringImport;
    }

    public function getWebhookConfig(): WebhookConfig
    {
        return $this->webhookConfig;
    }
}
