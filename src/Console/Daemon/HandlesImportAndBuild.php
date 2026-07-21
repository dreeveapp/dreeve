<?php

declare(strict_types=1);

namespace App\Console\Daemon;

use App\Application\AppVersion;
use App\Application\RebuildStatus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

trait HandlesImportAndBuild
{
    public const string IMPORT_OPTION = 'import';
    public const string BUILD_OPTION = 'build';
    public const string ONLY_IF_REQUIRED_OPTION = 'only-if-required';

    private function addImportAndBuildOptions(): void
    {
        $this->addOption(self::IMPORT_OPTION, null, InputOption::VALUE_NONE);
        $this->addOption(self::BUILD_OPTION, null, InputOption::VALUE_NONE);
        $this->addOption(self::ONLY_IF_REQUIRED_OPTION, null, InputOption::VALUE_NONE);
    }

    /**
     * @return array{import: bool, build: bool}
     */
    private function resolvePhases(InputInterface $input): array
    {
        $runImport = (bool) $input->getOption(self::IMPORT_OPTION);
        $runBuild = (bool) $input->getOption(self::BUILD_OPTION);

        if (!$runImport && !$runBuild) {
            throw new InvalidOptionException(sprintf('At least one of --%s or --%s must be provided.', self::IMPORT_OPTION, self::BUILD_OPTION));
        }

        return [self::IMPORT_OPTION => $runImport, self::BUILD_OPTION => $runBuild];
    }

    private function buildIsRequired(
        InputInterface $input,
        KeyValueStore $keyValueStore,
        RebuildStatus $rebuildStatus,
        string $today,
    ): bool {
        if (!$this->resolvePhases($input)[self::BUILD_OPTION]) {
            return false;
        }

        if (!$input->getOption(self::ONLY_IF_REQUIRED_OPTION)) {
            return true;
        }

        try {
            $appLastBuildSnapshot = (string) $keyValueStore->find(Key::APP_LAST_BUILD_SNAPSHOT);
        } catch (EntityNotFound) {
            return true;
        }
        if ($appLastBuildSnapshot !== $this->buildSnapshot($today)) {
            return true;
        }

        return $rebuildStatus->isPending();
    }

    private function markAppAsBuilt(
        KeyValueStore $keyValueStore,
        string $today,
    ): void {
        $keyValueStore->save(KeyValue::fromState(
            key: Key::APP_LAST_BUILD_SNAPSHOT,
            value: Value::fromString($this->buildSnapshot($today)),
        ));
        $keyValueStore->clear(Key::FORCE_REBUILD);
    }

    private function buildSnapshot(string $today): string
    {
        return $today.'@'.AppVersion::getSemanticVersion();
    }
}
