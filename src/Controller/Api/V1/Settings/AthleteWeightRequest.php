<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Settings;

use App\Infrastructure\Exception\CorruptedData;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[Exclude]
final readonly class AthleteWeightRequest
{
    private function __construct(
        private float $weight,
        private SerializableDateTime $on,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        try {
            $payload = Json::decode($request->getContent());
        } catch (CorruptedData) {
            throw new BadRequestHttpException('The request body must be a valid JSON object.');
        }

        if (!is_array($payload)) {
            throw new BadRequestHttpException('The request body must be a valid JSON object.');
        }

        $weight = $payload['weight'] ?? null;
        if (!is_numeric($weight) || (float) $weight <= 0) {
            throw new BadRequestHttpException('"weight" must be a positive number.');
        }

        $on = $payload['on'] ?? null;
        if (!is_string($on) || '' === $on) {
            throw new BadRequestHttpException('"on" is required.');
        }

        if (!SerializableDateTime::isValidDateString($on)) {
            throw new BadRequestHttpException('"on" must be a date in YYYY-MM-DD format.');
        }

        return new self(
            weight: (float) $weight,
            on: SerializableDateTime::fromString($on),
        );
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function getOn(): SerializableDateTime
    {
        return $this->on;
    }
}
