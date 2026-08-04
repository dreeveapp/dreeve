<?php

namespace App\Application\Import\StravaImport\ImportGear;

use App\Domain\Gear\Gear;
use App\Domain\Gear\GearIdRepository;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\GearType;
use App\Domain\Strava\RateLimit\StravaRateLimitHasBeenReached;
use App\Domain\Strava\Strava;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Time\Clock\Clock;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

final readonly class ImportGearCommandHandler implements CommandHandler
{
    public function __construct(
        private Strava $strava,
        private GearIdRepository $gearIdRepository,
        private GearRepository $gearRepository,
        private Clock $clock,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof ImportGear);
        $command->getOutput()->writeln('Importing gear...');

        $this->strava->setConsoleOutput($command->getOutput());

        $stravaGearIdsToImport = $this->gearIdRepository->findUniqueStravaGearIds(null);
        if ($command->isPartialImport()) {
            // We only want to update gears that are referenced on the activities to be imported.
            $stravaGearIdsToImport = $this->gearIdRepository->findUniqueStravaGearIds($command->getRestrictToActivityIds());
        }

        foreach ($stravaGearIdsToImport as $gearId) {
            try {
                $stravaGear = $this->strava->getGear($gearId);
            } catch (StravaRateLimitHasBeenReached $exception) {
                $command->getOutput()->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

                return;
            } catch (ClientException|RequestException $exception) {
                if (!$exception->getResponse() instanceof ResponseInterface) {
                    // Re-throw, we only want to catch supported error codes.
                    throw $exception;  // @codeCoverageIgnore
                }

                $command->getOutput()->writeln(sprintf('<error>Strava API threw error: %s</error>', $exception->getMessage()));

                return;
            }

            try {
                $gear = $this->gearRepository->find($gearId)
                    ->withName($stravaGear['name'])
                    ->withIsRetired($stravaGear['retired'] ?? false);
                $this->gearRepository->update($gear);
            } catch (EntityNotFound) {
                $gear = Gear::create(
                    gearId: $gearId,
                    createdOn: $this->clock->getCurrentDateTimeImmutable(),
                    name: $stravaGear['name'],
                    isRetired: $stravaGear['retired'] ?? false,
                    type: GearType::IMPORTED,
                );
                $this->gearRepository->add($gear);
            }
            $command->getOutput()->writeln(sprintf('  => Imported gear "%s"', $gear->getName()));
        }
    }
}
