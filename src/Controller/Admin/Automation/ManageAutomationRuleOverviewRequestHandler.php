<?php

declare(strict_types=1);

namespace App\Controller\Admin\Automation;

use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\AutomationRuleRepository;
use App\Domain\Automation\Backfill\AutomationRulesBackfillQueue;
use App\Domain\Automation\Condition\Conditions;
use App\Domain\Automation\SaveAutomationRuleOrder\SaveAutomationRuleOrder;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageAutomationRuleOverviewRequestHandler
{
    public function __construct(
        private Environment $twig,
        private AutomationRuleRepository $automationRuleRepository,
        private AutomationRulesBackfillQueue $backfillQueue,
        private Conditions $conditions,
        private Actions $actions,
    ) {
    }

    #[Route(path: '/admin/automation-rules', name: 'admin_manage_automation_rules_overview', methods: ['GET'], priority: 10)]
    public function handle(): HtmlResponse
    {
        $automationRules = $this->automationRuleRepository->findAll();

        return new HtmlResponse($this->twig->render('html/admin/page/automation-rules/manage-automation-rules-overview.html.twig', [
            'saveOrderCommand' => SaveAutomationRuleOrder::getCommandName(),
            'automationRules' => $automationRules,
            'backfillIsQueued' => $this->backfillQueue->isQueued(),
            'conditions' => $this->conditions->all(),
            'actions' => $this->actions->all(),
        ]));
    }
}
