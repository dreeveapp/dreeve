<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final readonly class Setting
{
    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string')]
        private SettingsGroup $settingsGroup,
        #[ORM\Id, ORM\Column(type: 'string')]
        private string $name,
        #[ORM\Column(type: 'text')]
        private string $value,
    ) {
    }

    public static function fromState(
        SettingsGroup $settingsGroup,
        string $name,
        string $value,
    ): self {
        return new self(
            settingsGroup: $settingsGroup,
            name: $name,
            value: $value,
        );
    }

    public function getSettingsGroup(): SettingsGroup
    {
        return $this->settingsGroup;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
