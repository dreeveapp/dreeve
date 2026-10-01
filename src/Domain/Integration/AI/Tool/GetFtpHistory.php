<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Settings\SettingsRepository;
use NeuronAI\Tools\Tool;

final class GetFtpHistory extends Tool
{
    #[\Override]
    protected string $name = 'get_ftp_history';

    #[\Override]
    protected ?string $description = <<<DESC
        Retrieves the athlete’s Functional Threshold Power (FTP) history from the database, providing a timeline of FTP values with corresponding dates.
        Use this tool when the user asks about changes in their FTP over time or when you need FTP to determine training intensity. 
        Example requests include “Show my FTP progression over the last 6 months” or “What was my FTP during my last ride?”
        DESC;

    public function __construct(
        private readonly SettingsRepository $settingsRepository,
    ) {
    }

    /**
     * @return array<int, mixed>
     */
    public function __invoke(): array
    {
        return $this->settingsRepository->general()->getFtpHistory()->exportForAITooling();
    }
}
