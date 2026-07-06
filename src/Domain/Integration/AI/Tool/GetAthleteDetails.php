<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Settings\SettingsRepository;
use NeuronAI\Tools\Tool;

final class GetAthleteDetails extends Tool
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository,
    ) {
        parent::__construct(
            'get_athlete_details',
            <<<DESC
            Retrieves the athlete’s personal details from the database.
            Use this tool when the user asks about their profile or personal information, such as weight, height, or other stored attributes. 
            Example requests include “Show my profile details”.
            DESC
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return $this->settingsRepository->general()->getAthlete()->exportForAITooling();
    }
}
