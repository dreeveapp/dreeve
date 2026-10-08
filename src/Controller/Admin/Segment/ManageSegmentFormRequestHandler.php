<?php

declare(strict_types=1);

namespace App\Controller\Admin\Segment;

use App\Domain\Import\ImportMode;
use App\Domain\Segment\AddCustomSegment\AddCustomSegment;
use App\Domain\Segment\DeleteSegment\DeleteSegment;
use App\Domain\Segment\Segment;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\UpdateSegment\UpdateSegment;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
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
        private SegmentRepository $segmentRepository,
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

    #[Route(path: '/admin/segments/{segmentId}/edit', name: 'admin_edit_segment', requirements: ['segmentId' => 'segment-[^/]+'], methods: ['GET'], priority: 10)]
    public function handleEdit(string $segmentId): HtmlResponse
    {
        $segment = $this->findSegment($segmentId);
        if ($segment->getType()->isImported()) {
            throw new NotFoundHttpException('Segments imported from Strava cannot be edited');
        }

        return new HtmlResponse($this->twig->render('html/admin/page/segment/edit-segment.html.twig', [
            'dispatchCommand' => UpdateSegment::getCommandName(),
            'segment' => $segment,
        ]));
    }

    #[Route(path: '/admin/segments/{segmentId}/delete', name: 'admin_delete_segment', requirements: ['segmentId' => 'segment-[^/]+'], methods: ['GET'], priority: 10)]
    public function handleDelete(string $segmentId): HtmlResponse
    {
        $segment = $this->findSegment($segmentId);
        if (!$segment->isDeletableIn($this->importMode)) {
            throw new NotFoundHttpException('Segment cannot be deleted');
        }

        return new HtmlResponse($this->twig->render('html/admin/page/segment/delete-segment.html.twig', [
            'dispatchCommand' => DeleteSegment::getCommandName(),
            'segment' => $segment,
        ]));
    }

    private function findSegment(string $segmentId): Segment
    {
        if (!$this->importMode->isFiles()) {
            throw new NotFoundHttpException('Page not found');
        }

        try {
            return $this->segmentRepository->find(SegmentId::fromString($segmentId));
        } catch (EntityNotFound) {
            throw new NotFoundHttpException('Segment not found');
        }
    }
}
