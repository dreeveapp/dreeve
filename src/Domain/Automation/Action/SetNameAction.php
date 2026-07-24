<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityName;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\Tokenizer\Tokenizer;
use App\Infrastructure\Tokenizer\TokenizerContext;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SetNameAction implements Action
{
    public function __construct(
        private Tokenizer $tokenizer,
    ) {
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Set name', domain: 'admin', locale: $locale);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return $configuration->getString('name');
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function getTemplateName(): string
    {
        return 'automation-action--set-name';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'name' => '',
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $name = $configuration->get('name');
        if (!is_string($name) || '' === trim($name)) {
            throw new InvalidAutomationRule('A "name" is required.');
        }
        if ([] !== $invalidTokens = $this->tokenizer->findInvalidTokens($name)) {
            throw new InvalidAutomationRule(sprintf('Unknown token(s): %s.', implode(', ', $invalidTokens)));
        }
    }

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity
    {
        $name = $this->tokenizer->replace(
            text: $configuration->getString('name'),
            context: TokenizerContext::empty()->with($activity)
        );

        return $activity->withName(ActivityName::fromString($name));
    }
}
