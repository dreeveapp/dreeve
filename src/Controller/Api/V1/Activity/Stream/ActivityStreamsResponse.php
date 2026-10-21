<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity\Stream;

use App\Domain\Activity\Stream\ActivityStreams;
use App\Domain\Activity\Stream\StreamType;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ActivityStreamsResponse extends JsonResponse
{
    public static function fromStreams(ActivityStreams $streams): self
    {
        $data = [];
        foreach (StreamType::cases() as $streamType) {
            $data[$streamType->getApiKey()] = $streams->filterOnType($streamType)?->getData() ?: null;
        }

        return new self($data);
    }
}
