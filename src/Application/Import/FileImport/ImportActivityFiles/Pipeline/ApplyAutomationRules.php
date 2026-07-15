<?php

declare(strict_types=1);

namespace App\Application\Import\FileImport\ImportActivityFiles\Pipeline;

use App\Domain\Automation\AutomationRuleEngine;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 95)]
final readonly class ApplyAutomationRules implements ImportActivityFileStep
{
    public function __construct(
        private AutomationRuleEngine $engine,
    ) {
    }

    public function process(ActivityImportContext $context): ActivityImportContext
    {
        $activity = $context->getActivity() ?? throw new \RuntimeException('Activity not set on $context');

        return $context->withActivity($this->engine->apply($activity));
    }
}
