<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.dashboard.widget')]
interface Widget
{
    public function getLabel(): string;

    public function getTemplateName(): string;

    public function getDefaultConfiguration(): WidgetConfiguration;

    public function guardValidConfiguration(WidgetConfiguration $configuration): void;

    public function render(SerializableDateTime $now, WidgetConfiguration $configuration): ?string;
}
