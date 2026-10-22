<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Settings\SettingsRepository;

final readonly class GetFtpHistory implements AITool
{
    public function __construct(
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function getName(): string
    {
        return 'get_ftp_history';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves the athlete’s Functional Threshold Power (FTP) history from the database, providing a timeline of FTP values with corresponding dates.
            Use this tool when the user asks about changes in their FTP over time or when you need FTP to determine training intensity.
            Example requests include “Show my FTP progression over the last 6 months” or “What was my FTP during my last ride?”
            DESC;
    }

    public function getParameters(): array
    {
        return [];
    }

    public function __invoke(ToolArguments $arguments): array
    {
        return $this->settingsRepository->general()->getFtpHistory()->exportForAITooling();
    }
}
