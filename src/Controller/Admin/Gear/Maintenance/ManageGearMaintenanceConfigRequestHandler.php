<?php

declare(strict_types=1);

namespace App\Controller\Admin\Gear\Maintenance;

use App\Domain\Gear\Gear;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\UpdateGearMaintenanceConfig\UpdateGearMaintenanceConfig;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsController]
final readonly class ManageGearMaintenanceConfigRequestHandler
{
    public function __construct(
        private Environment $twig,
        private GearMaintenanceRepository $gearMaintenanceRepository,
        private GearRepository $gearRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/admin/gear/maintenance-config', name: 'admin_manage_gear_maintenance_config', methods: ['GET'], priority: 10)]
    public function handle(): Response
    {
        $gearMaintenanceConfig = $this->gearMaintenanceRepository->find();
        $gears = $this->gearRepository->findAll();

        $warnings = [];

        $gearIdsInDb = GearIds::fromArray($gears->map(fn (Gear $gear): GearId => $gear->getId()));
        /** @var GearId $referencedGearId */
        foreach ($gearMaintenanceConfig->getAllReferencedGearIds() as $referencedGearId) {
            if ($gearIdsInDb->has($referencedGearId)) {
                continue;
            }

            $warnings[] = $this->translator->trans(
                'Gear "{gearId}" is attached to one of your components, but does not exist. It will be ignored.',
                ['{gearId}' => $referencedGearId->toUnprefixedString()],
                'admin'
            );
        }

        return new Response($this->twig->render('html/admin/page/gear/maintenance/config.html.twig', [
            'dispatchCommand' => UpdateGearMaintenanceConfig::getCommandName(),
            'gearMaintenanceConfig' => $gearMaintenanceConfig,
            'gears' => $gears,
            'warnings' => $warnings,
        ]));
    }
}
