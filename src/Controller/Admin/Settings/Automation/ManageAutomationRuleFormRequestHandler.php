<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings\Automation;

use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\AddAutomationRule\AddAutomationRule;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\AutomationRuleRepository;
use App\Domain\Automation\Condition\Conditions;
use App\Domain\Automation\DeleteAutomationRule\DeleteAutomationRule;
use App\Domain\Automation\UpdateAutomationRule\UpdateAutomationRule;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageAutomationRuleFormRequestHandler
{
    public function __construct(
        private Environment $twig,
        private AutomationRuleRepository $automationRuleRepository,
        private Conditions $conditions,
        private Actions $actions,
        private GearRepository $gearRepository,
        private RecordingDeviceRepository $recordingDeviceRepository,
    ) {
    }

    #[Route(path: '/admin/settings/automation-rules/add', name: 'admin_add_automation_rule', methods: ['GET'], priority: 10)]
    public function handleAdd(): Response
    {
        return new Response($this->twig->render('html/admin/page/settings/automation-rules/edit-automation-rule.html.twig', [
            'dispatchCommand' => AddAutomationRule::getCommandName(),
            'conditions' => $this->conditions->all(),
            'actions' => $this->actions->all(),
            'gears' => $this->gearRepository->findAll(),
            'recordingDevices' => $this->recordingDeviceRepository->findAll(),
        ]));
    }

    #[Route(path: '/admin/settings/automation-rules/{id}/edit', name: 'admin_edit_automation_rule', methods: ['GET'], priority: 10)]
    public function handleEdit(string $id): Response
    {
        return new Response($this->twig->render('html/admin/page/settings/automation-rules/edit-automation-rule.html.twig', [
            'dispatchCommand' => UpdateAutomationRule::getCommandName(),
            'automationRule' => $this->automationRuleRepository->find(AutomationRuleId::fromString($id)),
            'conditions' => $this->conditions->all(),
            'actions' => $this->actions->all(),
            'gears' => $this->gearRepository->findAll(),
            'recordingDevices' => $this->recordingDeviceRepository->findAll(),
        ]));
    }

    #[Route(path: '/admin/settings/automation-rules/{id}/delete', name: 'admin_delete_automation_rule', methods: ['GET'], priority: 10)]
    public function handleDelete(string $id): Response
    {
        return new Response($this->twig->render('html/admin/page/settings/automation-rules/delete-automation-rule.html.twig', [
            'dispatchCommand' => DeleteAutomationRule::getCommandName(),
            'automationRule' => $this->automationRuleRepository->find(AutomationRuleId::fromString($id)),
        ]));
    }
}
