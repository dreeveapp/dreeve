<?php

declare(strict_types=1);

namespace App\Domain\Settings\UpdateAthleteFtpHistory;

use App\Domain\Ftp\FtpSport;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class UpdateAthleteFtpHistoryCommandHandler implements CommandHandler
{
    public function __construct(
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateAthleteFtpHistory);

        $data = $this->settingsRepository->find(SettingsGroup::GENERAL);
        /** @var array<string, mixed> $athlete */
        $athlete = is_array($data['athlete'] ?? null) ? $data['athlete'] : [];

        $ftpHistory = $this->normalizeExistingHistory(is_array($athlete['ftpHistory'] ?? null) ? $athlete['ftpHistory'] : []);
        // Replace only the requested sport, leaving the other one untouched.
        $ftpHistory[$command->getSport()->value] = $command->getEntries();

        $athlete['ftpHistory'] = $ftpHistory;
        $data['athlete'] = $athlete;

        $this->settingsRepository->save(
            group: SettingsGroup::GENERAL,
            data: $data,
        );
    }

    /**
     * A history predating the cycling/running split is a bare list of entries.
     * FtpHistory reads that as cycling, so migrate it to the keyed shape before
     * writing, otherwise the legacy entries would sit alongside the new key and
     * be silently dropped on the next read.
     *
     * @param array<int|string, mixed> $ftpHistory
     *
     * @return array<string, mixed>
     */
    private function normalizeExistingHistory(array $ftpHistory): array
    {
        foreach (FtpSport::cases() as $sport) {
            if (array_key_exists($sport->value, $ftpHistory)) {
                /* @var array<string, mixed> $ftpHistory */
                return $ftpHistory;
            }
        }

        if ([] === $ftpHistory) {
            return [];
        }

        return [FtpSport::CYCLING->value => array_values($ftpHistory)];
    }
}
