<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

use App\Domain\Activity\Activity;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\Tokenizer\Tokenizer;
use App\Infrastructure\Tokenizer\TokenizerContext;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SetDescriptionAction implements Action
{
    public function __construct(
        private Tokenizer $tokenizer,
    ) {
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Set description', domain: 'admin', locale: $locale);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return $configuration->getString('description');
    }

    public function getPriority(): int
    {
        return 60;
    }

    public function getTemplateName(): string
    {
        return 'automation-action--set-description';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'description' => '',
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $description = $configuration->get('description');
        if (!is_string($description)) {
            return;
        }
        if ([] !== $invalidTokens = $this->tokenizer->findInvalidTokens($description)) {
            throw new InvalidAutomationRule(sprintf('Unknown token(s): %s.', implode(', ', $invalidTokens)));
        }
    }

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity
    {
        $description = $this->tokenizer->replace(
            text: $configuration->getString('description'),
            context: TokenizerContext::empty()->with($activity)
        );

        return $activity->withDescription($description);
    }
}
