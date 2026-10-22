<?php

declare(strict_types=1);

namespace App\Domain\Integration\GitHub;

use App\Infrastructure\Serialization\Json;
use GuzzleHttp\Client;

final readonly class GitHub
{
    public function __construct(
        private Client $client,
    ) {
    }

    public function getLatestRelease(): string
    {
        $response = $this->client->request(
            method: 'GET',
            uri: 'https://api.github.com/repos/dreeveapp/dreeve/releases/latest'
        );

        $json = $response->getBody()->getContents();

        return Json::decode($json)['name'];
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<int, array<string, mixed>>
     */
    public function getWorkflowRuns(array $query = []): array
    {
        $response = $this->client->request(
            method: 'GET',
            uri: 'https://api.github.com/repos/dreeveapp/dreeve/actions/runs',
            options: ['query' => $query]
        );

        $json = $response->getBody()->getContents();

        return Json::decode($json)['workflow_runs'];
    }
}
