<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Challenge\Challenge;
use App\Domain\Challenge\ChallengeRepository;
use NeuronAI\Tools\Tool;

final class GetChallenges extends Tool
{
    private const int MAX_RESULTS = 50;

    public function __construct(
        private readonly ChallengeRepository $challengeRepository,
    ) {
        parent::__construct(
            'get_challenges',
            <<<DESC
            Retrieves the user’s most recently obtained challenges, including challenge name, start date, and completion date.
            Use this tool when the user asks about current, upcoming, or past challenges. 
            Example requests include “List the challenges I completed last month.”
            Returns at most 50 challenges, most recent first. Compare "totalChallengeCount" with the number of returned challenges: if it is higher, tell the user you are only showing the most recent ones and do not present the list as complete.
            DESC
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        $challenges = $this->challengeRepository->findAll();

        return [
            'challenges' => $challenges
                ->slice(0, self::MAX_RESULTS)
                ->map(fn (Challenge $challenge): array => $challenge->exportForAITooling()),
            'totalChallengeCount' => count($challenges),
        ];
    }
}
