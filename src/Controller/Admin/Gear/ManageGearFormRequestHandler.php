<?php

declare(strict_types=1);

namespace App\Controller\Admin\Gear;

use App\Domain\Gear\AddGear\AddGear;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\GearStatus;
use App\Domain\Gear\UpdateGear\UpdateGear;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageGearFormRequestHandler
{
    public function __construct(
        private Environment $twig,
        private GearRepository $gearRepository,
    ) {
    }

    #[Route(path: '/admin/gear/add', name: 'admin_add_gear', methods: ['GET'], priority: 10)]
    public function handleAdd(): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/gear/edit-gear.html.twig', [
            'dispatchCommand' => AddGear::getCommandName(),
            'statuses' => GearStatus::cases(),
        ]));
    }

    #[Route(path: '/admin/gear/{gearId}/edit', name: 'admin_edit_gear', methods: ['GET'], priority: 10)]
    public function handleEdit(string $gearId): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/gear/edit-gear.html.twig', [
            'dispatchCommand' => UpdateGear::getCommandName(),
            'gear' => $this->gearRepository->find(GearId::fromString($gearId)),
            'statuses' => GearStatus::cases(),
        ]));
    }
}
