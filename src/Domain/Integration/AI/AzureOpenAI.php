<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI;

/**
 * Workaround for neuron-ai 3.15.29, which dropped the default value of
 * $baseUri while AzureOpenAI::setBaseUrl() still uses it, making the vendor class unusable.
 *
 * Can be removed once the default is restored upstream.
 */
final class AzureOpenAI extends \NeuronAI\Providers\OpenAI\AzureOpenAI
{
    #[\Override]
    protected string $baseUri = 'https://%s/openai/deployments/%s';
}
