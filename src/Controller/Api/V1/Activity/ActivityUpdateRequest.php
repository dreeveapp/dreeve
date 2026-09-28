<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity;

use App\Infrastructure\Exception\CorruptedData;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[Exclude]
final readonly class ActivityUpdateRequest
{
    private const array ALLOWED_FIELDS = ['name', 'description', 'sportType'];

    /**
     * @param array<string, mixed> $fields
     */
    private function __construct(
        private array $fields,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        try {
            $payload = Json::decode($request->getContent());
        } catch (CorruptedData) {
            throw new BadRequestHttpException('The request body must be a valid JSON object.');
        }

        if (!is_array($payload) || array_is_list($payload)) {
            throw new BadRequestHttpException(sprintf('The request body must be a JSON object containing at least one of: %s.', implode(', ', self::ALLOWED_FIELDS)));
        }

        if ([] !== $unknownFields = array_diff(array_keys($payload), self::ALLOWED_FIELDS)) {
            throw new BadRequestHttpException(sprintf('Unknown field(s): %s. Allowed fields are: %s.', implode(', ', $unknownFields), implode(', ', self::ALLOWED_FIELDS)));
        }

        return new self($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
