<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

use App\Domain\Activity\Activity;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Infrastructure\Exception\EntityNotFound;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AssignGearAction implements Action
{
    public function __construct(
        private GearRepository $gearRepository,
    ) {
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Assign gear', domain: 'admin', locale: $locale);
    }

    public function describe(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        $gearId = $configuration->getString('gearId');

        try {
            $gear = $this->gearRepository->find(GearId::fromString($gearId))->getName();
        } catch (EntityNotFound) {
            $gear = $gearId;
        }

        return $translator->trans('Assign gear {gear}', [
            'gear' => $gear,
        ], domain: 'admin');
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function getTemplateName(): string
    {
        return 'automation-action--assign-gear';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'gearId' => '',
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $gearId = $configuration->get('gearId');
        if (!is_string($gearId) || '' === trim($gearId)) {
            throw new InvalidAutomationRule('A "gearId" is required.');
        }
    }

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity
    {
        $gearId = $configuration->getString('gearId');

        return $activity->withGear(GearId::fromString($gearId));
    }
}
