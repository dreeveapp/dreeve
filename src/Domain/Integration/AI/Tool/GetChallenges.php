<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Challenge\Challenge;
use App\Domain\Challenge\ChallengeRepository;

final readonly class GetChallenges implements AITool
{
    private const int MAX_RESULTS = 50;

    public function __construct(
        private ChallengeRepository $challengeRepository,
    ) {
    }

    public function getName(): string
    {
        return 'get_challenges';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves the user’s most recently obtained challenges, including challenge name, start date, and completion date.
            Use this tool when the user asks about current, upcoming, or past challenges.
            Example requests include “List the challenges I completed last month.”
            Returns at most 50 challenges, most recent first. Compare "totalChallengeCount" with the number of returned challenges: if it is higher, tell the user you are only showing the most recent ones and do not present the list as complete.
            DESC;
    }

    public function getParameters(): array
    {
        return [];
    }

    public function __invoke(ToolArguments $arguments): array
    {
        $challenges = $this->challengeRepository->findAll();

        return [
            'challenges' => $challenges
                ->slice(0, self::MAX_RESULTS)
                ->map(static fn (Challenge $challenge): array => $challenge->exportForAITooling()),
            'totalChallengeCount' => count($challenges),
        ];
    }
}
