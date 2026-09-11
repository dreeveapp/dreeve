<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final readonly class Setting
{
    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string')]
        public SettingsGroup $settingsGroup,
        #[ORM\Id, ORM\Column(type: 'string')]
        public string $name,
        #[ORM\Column(type: 'text')]
        public string $value,
    ) {
    }
}
