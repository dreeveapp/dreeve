<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Settings\SettingsRepository;

final readonly class GetAthleteDetails implements AITool
{
    public function __construct(
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function getName(): string
    {
        return 'get_athlete_details';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves the athlete’s personal details from the database.
            Use this tool when the user asks about their profile or personal information, such as weight, height, or other stored attributes.
            Example requests include “Show my profile details”.
            DESC;
    }

    public function getParameters(): array
    {
        return [];
    }

    public function __invoke(ToolArguments $arguments): array
    {
        return $this->settingsRepository->general()->getAthlete()->exportForAITooling();
    }
}
