<?php

namespace App\Application\Import\StravaImport\ImportChallenges;

use App\Domain\Challenge\Challenge;
use App\Domain\Challenge\ChallengeId;
use App\Domain\Challenge\ChallengeRepository;
use App\Domain\Strava\Strava;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Time\Sleep;
use App\Infrastructure\ValueObject\Identifier\UuidFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Target;

final readonly class ImportChallengesCommandHandler implements CommandHandler
{
    public const string DEFAULT_STRAVA_CHALLENGE_HISTORY = '<!-- OVERRIDE ME WITH HTML COPY/PASTED FROM https://www.strava.com/athletes/[YOUR_ATHLETE_ID]/trophy-case -->';

    public function __construct(
        private Strava $strava,
        private ChallengeRepository $challengeRepository,
        #[Target('file.storage')]
        private FilesystemOperator $fileStorage,
        private UuidFactory $uuidFactory,
        private Sleep $sleep,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof ImportChallenges);
        $command->getOutput()->writeln('Importing challenges...');

        if (!$this->fileStorage->fileExists('strava-challenge-history.html')) {
            $this->fileStorage->write(
                location: 'strava-challenge-history.html',
                contents: self::DEFAULT_STRAVA_CHALLENGE_HISTORY
            );
        }

        $challenges = [];

        try {
            $challenges = $this->strava->getChallengesOnTrophyCase();
        } catch (\Throwable $e) {
            $command->getOutput()->writeln('Could not import challenges from trophy case page: '.$e->getMessage());
        }

        if ([] === $challenges) {
            $command->getOutput()->writeln('No challenges to import...');

            return;
        }

        foreach ($challenges as $stravaChallenge) {
            $createdOn = $stravaChallenge['completedOn'];
            $challengeId = ChallengeId::fromDateAndName(
                createdOn: $createdOn,
                name: $stravaChallenge['name'],
            );

            try {
                $this->challengeRepository->find($challengeId);
                continue;
            } catch (EntityNotFound) {
                $oldChallengeId = ChallengeId::toOldVersion($createdOn, $stravaChallenge['name']);
                if ($oldChallengeId->toUnprefixedString() !== $challengeId->toUnprefixedString()) {
                    try {
                        $this->challengeRepository->find($oldChallengeId);
                        $this->challengeRepository->updateChallengeId($oldChallengeId, $challengeId);
                        $command->getOutput()->writeln(sprintf('  => Migrated challenge "%s"', $stravaChallenge['name']));
                        continue;
                    } catch (EntityNotFound) {
                    }
                }
            }

            $challenge = Challenge::create(
                challengeId: $challengeId,
                createdOn: $createdOn,
                name: $stravaChallenge['name'],
                logoUrl: $stravaChallenge['logo_url'] ?? null,
                slug: $stravaChallenge['url'],
            );
            if ($url = $challenge->getLogoUrl()) {
                $fileSystemPath = sprintf('challenges/%s.png', $this->uuidFactory->random());
                try {
                    $this->fileStorage->write(
                        $fileSystemPath,
                        $this->strava->downloadImage($url)
                    );
                } catch (\Throwable $e) {
                    $command->getOutput()->writeln(sprintf(
                        '  => Could not import "%s", error: %s',
                        $challenge->getName(),
                        $e->getMessage()
                    ));
                    continue;
                }
                $challenge = $challenge->withLocalLogo('files/'.$fileSystemPath);
            }
            $this->challengeRepository->add($challenge);
            $command->getOutput()->writeln(sprintf('  => Imported challenge "%s"', $challenge->getName()));
            $this->sleep->sweetDreams(1); // Make sure timestamp is increased by at least one second.
        }

        $this->challengeRepository->deleteWithNonAlphanumericIds();
    }
}
