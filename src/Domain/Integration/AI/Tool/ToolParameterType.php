<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

enum ToolParameterType: string
{
    case STRING = 'string';
    case INTEGER = 'integer';
    case BOOLEAN = 'boolean';
}
