<?php

declare(strict_types=1);

namespace App\Controller\Admin\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\DeleteActivity\DeleteActivity;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\ManuallyCreateActivity\ManuallyCreateActivity;
use App\Domain\Activity\UpdateActivity\UpdateActivity;
use App\Domain\Activity\UpdateManuallyAddedActivity\UpdateManuallyAddedActivity;
use App\Domain\Activity\WorkoutType;
use App\Domain\Gear\Gear;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use App\Domain\Import\ImportMode;
use App\Domain\Settings\SettingsRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageActivityFormRequestHandler
{
    public function __construct(
        private Environment $twig,
        private ActivityRepository $activityRepository,
        private GearRepository $gearRepository,
        private RecordingDeviceRepository $recordingDeviceRepository,
        private SettingsRepository $settingsRepository,
        private ImportMode $importMode,
    ) {
    }

    #[Route(path: '/admin/activities/add', name: 'admin_add_activity', methods: ['GET'], priority: 10)]
    public function handleAdd(): Response
    {
        if (!$this->importMode->isFiles()) {
            throw new NotFoundHttpException('Activities can only be created manually when running in file import mode.');
        }

        return new Response($this->twig->render('html/admin/page/activity/edit-manually-added-activity.html.twig', [
            'dispatchCommand' => ManuallyCreateActivity::getCommandName(),
            'sportTypes' => $this->settingsRepository->appearance()->getSportTypesSortingOrder(),
            'workoutTypes' => WorkoutType::cases(),
            'gears' => $this->gearRepository->findAll()->sortByIsRetired(),
        ]));
    }

    #[Route(path: '/admin/activities/{activityId}/edit', name: 'admin_edit_activity', methods: ['GET'], priority: 10)]
    public function handleEdit(string $activityId): Response
    {
        $activity = $this->activityRepository->find(ActivityId::fromString($activityId));

        if (ImportSource::MANUAL === $activity->getImportSource()) {
            return new Response($this->twig->render('html/admin/page/activity/edit-manually-added-activity.html.twig', [
                'dispatchCommand' => UpdateManuallyAddedActivity::getCommandName(),
                'activity' => $activity,
                'sportTypes' => $this->settingsRepository->appearance()->getSportTypesSortingOrder(),
                'workoutTypes' => WorkoutType::cases(),
                'gears' => $this->gearRepository->findAll()->sortByIsRetired(),
            ]));
        }

        $gears = $this->gearRepository->findAll()->sortByIsRetired();
        $gearIsReadOnly = false;
        if ($this->importMode->isStravaApi()) {
            $currentGear = $activity->getGearId() instanceof GearId
                ? $gears->find(fn (Gear $gear): bool => $gear->getId()->matches($activity->getGearId()))
                : null;
            $gears = $gears->filter(fn (Gear $gear): bool => $gear->getType()->isCustom() || $gear === $currentGear);
            $gearIsReadOnly = $gears->isEmpty() || ($currentGear instanceof Gear && $currentGear->getType()->isImported());
        }

        return new Response($this->twig->render('html/admin/page/activity/edit-activity.html.twig', [
            'dispatchCommand' => UpdateActivity::getCommandName(),
            'activity' => $activity,
            'sportTypes' => $this->settingsRepository->appearance()->getSportTypesSortingOrder(),
            'gears' => $gears,
            'gearIsReadOnly' => $gearIsReadOnly,
            'recordingDevices' => $this->recordingDeviceRepository->findAll(),
        ]));
    }

    #[Route(path: '/admin/activities/{activityId}/delete', name: 'admin_delete_activity', methods: ['GET'], priority: 10)]
    public function handleDelete(string $activityId): Response
    {
        return new Response($this->twig->render('html/admin/page/activity/delete-activity.html.twig', [
            'dispatchCommand' => DeleteActivity::getCommandName(),
            'activity' => $this->activityRepository->find(ActivityId::fromString($activityId)),
        ]));
    }
}
