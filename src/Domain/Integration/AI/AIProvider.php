<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI;

enum AIProvider: string
{
    case ANTHROPIC = 'anthropic';
    case AZURE_OPEN_AI = 'azureOpenAI';
    case DEEPSEEK = 'deepseek';
    case GEMINI = 'gemini';
    case GROK = 'grok';
    case HUGGING_FACE = 'huggingFace';
    case OLLAMA = 'ollama';
    case OPEN_AI = 'openAI';
    case OPEN_AI_LIKE = 'openAILike';
    case OPEN_AI_RESPONSES = 'openAIResponses';
    case MISTRAL = 'mistral';

    /**
     * @return list<string>
     */
    public function getRequiredConfigKeys(): array
    {
        return match ($this) {
            self::OLLAMA => ['model', 'url'],
            self::AZURE_OPEN_AI => ['endpoint', 'model'],
            self::OPEN_AI_LIKE => ['baseUri', 'model'],
            default => ['model'],
        };
    }

    public function requiresApiKey(): bool
    {
        return self::OLLAMA !== $this;
    }
}
