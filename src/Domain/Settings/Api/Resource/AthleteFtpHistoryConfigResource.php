<?php

declare(strict_types=1);

namespace App\Domain\Settings\Api\Resource;

use App\Domain\Ftp\FtpSport;
use App\Domain\Settings\Api\WritableConfigResource;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateAthleteFtpHistory\UpdateAthleteFtpHistory;
use App\Infrastructure\CQRS\Command\Command;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

abstract readonly class AthleteFtpHistoryConfigResource implements WritableConfigResource
{
    public function __construct(
        // Deliberately the uncached repository: command handlers write through it
        // too, so reading the state back straight after a write would otherwise
        // hit a stale CachingSettingsRepository::find() entry.
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    abstract protected function sport(): FtpSport;

    #[\Override]
    public function getName(): string
    {
        return 'athlete/ftp-history/'.$this->sport()->value;
    }

    #[\Override]
    public function read(): array
    {
        $general = $this->settingsRepository->find(SettingsGroup::GENERAL);
        $athlete = is_array($general['athlete'] ?? null) ? $general['athlete'] : [];
        $ftpHistory = is_array($athlete['ftpHistory'] ?? null) ? $athlete['ftpHistory'] : [];

        $entries = $ftpHistory[$this->sport()->value] ?? null;
        if (!is_array($entries)) {
            // A history predating the cycling/running split is a bare list, which
            // FtpHistory reads as cycling. Mirror that here so GET matches
            // whatever the app actually uses.
            $isLegacyShape = !array_key_exists(FtpSport::CYCLING->value, $ftpHistory)
                && !array_key_exists(FtpSport::RUNNING->value, $ftpHistory);

            $entries = $isLegacyShape && FtpSport::CYCLING === $this->sport() ? $ftpHistory : [];
        }

        return [
            'sport' => $this->sport()->value,
            'entries' => array_values($entries),
        ];
    }

    #[\Override]
    public function buildUpdateCommand(array $payload): Command
    {
        // The sport comes from the URL, not the body, so a client cannot address
        // one resource and write another.
        $payload['sport'] = $this->sport()->value;

        return UpdateAthleteFtpHistory::fromPayload($payload);
    }
}
