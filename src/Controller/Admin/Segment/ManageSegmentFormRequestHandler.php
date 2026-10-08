<?php

declare(strict_types=1);

namespace App\Controller\Admin\Segment;

use App\Domain\Import\ImportMode;
use App\Domain\Segment\AddCustomSegment\AddCustomSegment;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageSegmentFormRequestHandler
{
    public function __construct(
        private Environment $twig,
        private SettingsRepository $settingsRepository,
        private ImportMode $importMode,
    ) {
    }

    #[Route(path: '/admin/segments/add', name: 'admin_add_segment', methods: ['GET'], priority: 10)]
    public function handleAdd(): HtmlResponse
    {
        if (!$this->importMode->isFiles()) {
            throw new NotFoundHttpException('Page not found');
        }

        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();

        return new HtmlResponse($this->twig->render('html/admin/page/segment/add-segment.html.twig', [
            'dispatchCommand' => AddCustomSegment::getCommandName(),
            'unitSystem' => $unitSystem,
            'unitFactors' => [
                'distanceInMetersPerUnit' => $unitSystem->distance(1)->toMeter()->toFloat(),
                'elevationInMetersPerUnit' => $unitSystem->elevation(1)->toMeter()->toFloat(),
            ],
        ]));
    }
}
