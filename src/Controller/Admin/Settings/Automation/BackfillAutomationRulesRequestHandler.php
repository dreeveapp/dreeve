<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings\Automation;

use App\Domain\Automation\AutomationRule;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\AutomationRuleIds;
use App\Domain\Automation\AutomationRuleRepository;
use App\Domain\Automation\AutomationRules;
use App\Domain\Automation\Backfill\AutomationRulesBackfillPreviewer;
use App\Domain\Automation\Backfill\AutomationRulesBackfillQueue;
use App\Domain\Automation\Backfill\MatchedActivity;
use App\Domain\Automation\Backfill\QueueAutomationRulesBackfill\QueueAutomationRulesBackfill;
use App\Domain\Import\ImportMode;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class BackfillAutomationRulesRequestHandler
{
    public function __construct(
        private Environment $twig,
        private AutomationRuleRepository $automationRuleRepository,
        private AutomationRulesBackfillPreviewer $previewer,
        private AutomationRulesBackfillQueue $backfillQueue,
        private ImportMode $importMode,
    ) {
    }

    #[Route(path: '/admin/settings/automation-rules/backfill', name: 'admin_backfill_automation_rules', methods: ['GET'], priority: 20)]
    public function handle(Request $request): HtmlResponse
    {
        if (!$this->importMode->isFiles()) {
            throw new NotFoundHttpException('Automation rules are only available in file import mode');
        }

        $enabledRules = $this->automationRuleRepository->findAll()->enabled();
        if ($enabledRules->isEmpty()) {
            throw new NotFoundHttpException('There are no enabled automation rules to apply');
        }

        $selectedIds = $this->selectedRules($request, $enabledRules)->map(
            static fn (AutomationRule $automationRule): string => (string) $automationRule->getId()
        );

        return new HtmlResponse($this->twig->render('html/admin/page/settings/automation-rules/backfill-automation-rules.html.twig', [
            'isQueued' => $this->backfillQueue->isQueued(),
            'automationRules' => $enabledRules->map(
                static fn (AutomationRule $automationRule): array => [
                    'id' => (string) $automationRule->getId(),
                    'label' => $automationRule->getLabel(),
                    'isSelected' => in_array((string) $automationRule->getId(), $selectedIds, true),
                ]
            ),
            'selectedAutomationRuleIds' => $selectedIds,
        ]));
    }

    #[Route(path: '/admin/settings/automation-rules/backfill/preview', name: 'admin_backfill_automation_rules_preview', methods: ['GET'], priority: 20)]
    public function preview(Request $request): HtmlResponse
    {
        if (!$this->importMode->isFiles()) {
            throw new NotFoundHttpException('Automation rules are only available in file import mode');
        }

        $enabledRules = $this->automationRuleRepository->findAll()->enabled();
        if ($enabledRules->isEmpty()) {
            throw new NotFoundHttpException('There are no enabled automation rules to apply');
        }

        if ($this->backfillQueue->isQueued()) {
            throw new NotFoundHttpException('An automation rules backfill is already queued');
        }

        $selectedRules = $this->selectedRules($request, $enabledRules);
        if ($selectedRules->isEmpty()) {
            throw new NotFoundHttpException('None of the selected automation rules are enabled');
        }

        $preview = $this->previewer->preview($selectedRules);

        $ruleLabels = [];
        foreach ($selectedRules as $automationRule) {
            $ruleLabels[(string) $automationRule->getId()] = $automationRule->getLabel();
        }

        return new HtmlResponse($this->twig->render('html/admin/page/settings/automation-rules/_backfill-preview.html.twig', [
            'totalScanned' => $preview->getTotalScanned(),
            'totalMatched' => count($preview->getMatchedActivities()),
            'matchedActivities' => array_map(
                static fn (MatchedActivity $matchedActivity): array => [
                    'id' => (string) $matchedActivity->getActivity()->getId(),
                    'name' => $matchedActivity->getActivity()->getName(),
                    'startDate' => $matchedActivity->getActivity()->getStartDate()->format('d-m-Y'),
                    'ruleLabels' => $matchedActivity->getAppliedAutomationRuleIds()->map(
                        static fn (AutomationRuleId $automationRuleId): string => $ruleLabels[(string) $automationRuleId] ?? (string) $automationRuleId
                    ),
                ],
                $preview->getMatchedActivities()
            ),
            'selectedAutomationRuleIds' => $selectedRules->map(
                static fn (AutomationRule $automationRule): string => (string) $automationRule->getId()
            ),
            'queueCommand' => QueueAutomationRulesBackfill::getCommandName(),
        ]));
    }

    private function selectedRules(Request $request, AutomationRules $enabledRules): AutomationRules
    {
        $selectedIds = array_filter($request->query->all('automationRuleIds'), is_string(...));
        if ([] === $selectedIds) {
            return AutomationRules::empty();
        }

        return $enabledRules->only(AutomationRuleIds::fromArray(array_map(
            AutomationRuleId::fromPrefixedOrUnprefixed(...),
            $selectedIds
        )));
    }
}
