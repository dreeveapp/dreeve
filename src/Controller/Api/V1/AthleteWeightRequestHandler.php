<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateAthleteSettings\UpdateAthleteSettings;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Http\Api\ApiErrorResponse;
use App\Infrastructure\Time\Clock\Clock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AthleteWeightRequestHandler
{
    public function __construct(
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
        private CommandBus $commandBus,
        private Clock $clock,
    ) {
    }

    #[Route(path: '/api/v1/athlete/weights', name: 'api_v1_athlete_weights', methods: ['POST'], priority: 3)]
    public function handle(Request $request): Response
    {
        if (!str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
                error: 'unsupported_media_type',
                message: 'Send the weight as application/json.',
            );
        }

        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_BAD_REQUEST,
                error: 'bad_request',
                message: 'The request body must be valid JSON.',
            );
        }

        $weight = $payload['weight'] ?? null;
        if ((!is_int($weight) && !is_float($weight)) || !is_finite((float) $weight) || $weight <= 0) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_BAD_REQUEST,
                error: 'bad_request',
                message: 'A positive numeric "weight" is required.',
            );
        }

        $on = array_key_exists('on', $payload)
            ? $payload['on']
            : $this->clock->getCurrentDateTimeImmutable()->format('Y-m-d');
        if (!is_string($on) || !$this->isDate($on)) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_BAD_REQUEST,
                error: 'bad_request',
                message: '"on" must be a date in YYYY-MM-DD format.',
            );
        }

        $general = $this->settingsRepository->find(SettingsGroup::GENERAL);
        /** @var array<string, mixed> $athlete */
        $athlete = $general['athlete'] ?? [];
        /** @var list<mixed> $weightHistory */
        $weightHistory = is_array($athlete['weightHistory'] ?? null) ? $athlete['weightHistory'] : [];

        $exists = false;
        $updatedWeightHistory = [];
        foreach ($weightHistory as $entry) {
            if (is_array($entry) && $on === ($entry['on'] ?? null)) {
                $exists = true;
                continue;
            }

            $updatedWeightHistory[] = $entry;
        }
        $updatedWeightHistory[] = ['on' => $on, 'weight' => (float) $weight];
        $athlete['weightHistory'] = $updatedWeightHistory;

        $this->commandBus->dispatch(UpdateAthleteSettings::fromPayload(['athlete' => $athlete]));

        return new JsonResponse([
            'status' => $exists ? 'updated' : 'created',
            'on' => $on,
            'weight' => (float) $weight,
        ], $exists ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    private function isDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();

        return $parsed instanceof \DateTimeImmutable
            && false === $errors
            && $date === $parsed->format('Y-m-d');
    }
}
