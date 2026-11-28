<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\MediaLocation;

enum LocationSuggestionConfidence: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
}
