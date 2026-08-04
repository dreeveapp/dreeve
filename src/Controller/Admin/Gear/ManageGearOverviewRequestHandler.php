<?php

declare(strict_types=1);

namespace App\Controller\Admin\Gear;

use App\Domain\Gear\GearRepository;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageGearOverviewRequestHandler
{
    public function __construct(
        private Environment $twig,
        private GearRepository $gearRepository,
    ) {
    }

    #[Route(path: '/admin/gear', name: 'admin_manage_gear_overview', methods: ['GET'], priority: 10)]
    public function handle(): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/gear/manage-gear-overview.html.twig', [
            'gears' => $this->gearRepository->findAll(),
        ]));
    }
}
