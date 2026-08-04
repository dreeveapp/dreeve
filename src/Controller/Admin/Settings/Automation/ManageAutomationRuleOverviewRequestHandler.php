<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings\Automation;

use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\AutomationRuleRepository;
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
        private Conditions $conditions,
        private Actions $actions,
    ) {
    }

    #[Route(path: '/admin/settings/automation-rules', name: 'admin_manage_automation_rules_overview', methods: ['GET'], priority: 10)]
    public function handle(): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/settings/automation-rules/manage-automation-rules-overview.html.twig', [
            'saveOrderCommand' => SaveAutomationRuleOrder::getCommandName(),
            'automationRules' => $this->automationRuleRepository->findAll(),
            'conditions' => $this->conditions->all(),
            'actions' => $this->actions->all(),
        ]));
    }
}
