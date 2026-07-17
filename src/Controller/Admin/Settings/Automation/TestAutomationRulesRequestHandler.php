<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings\Automation;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\AutomationRuleRepository;
use App\Domain\Automation\Condition\Conditions;
use App\Domain\Automation\DryRun\AutomationRuleDryRunner;
use App\Infrastructure\Exception\EntityNotFound;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class TestAutomationRulesRequestHandler
{
    public function __construct(
        private Environment $twig,
        private ActivityRepository $activityRepository,
        private AutomationRuleRepository $automationRuleRepository,
        private AutomationRuleDryRunner $dryRunner,
        private Conditions $conditions,
        private Actions $actions,
    ) {
    }

    #[Route(path: '/admin/settings/automation-rules/test', name: 'admin_test_automation_rules', methods: ['GET'], priority: 20)]
    public function handle(Request $request): Response
    {
        if ($this->automationRuleRepository->findAll()->isEmpty()) {
            throw new NotFoundHttpException('There are no automation rules to test');
        }

        $activityId = $request->query->get('activityId');

        $dryRun = null;
        $notFound = false;

        if (null !== $activityId) {
            try {
                $activity = $this->activityRepository->find(ActivityId::fromPrefixedOrUnprefixed($activityId));
                $dryRun = $this->dryRunner->run($activity);
            } catch (EntityNotFound|\InvalidArgumentException) {
                $notFound = true;
            }
        }

        return new Response($this->twig->render('html/admin/page/settings/automation-rules/test-automation-rules.html.twig', [
            'activityId' => $activityId,
            'dryRun' => $dryRun,
            'notFound' => $notFound,
            'conditions' => $this->conditions->all(),
            'actions' => $this->actions->all(),
        ]));
    }
}
