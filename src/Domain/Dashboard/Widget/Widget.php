<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Dashboard\DashboardWidgetId;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.dashboard.widget')]
interface Widget
{
    public function getLabel(): string;

    public function getTemplateName(): string;

    public function getCacheTags(): CacheTags;

    public function getDefaultConfiguration(): WidgetConfiguration;

    public function guardValidConfiguration(WidgetConfiguration $configuration): void;

    public function render(DashboardWidgetId $dashboardWidgetId, SerializableDateTime $now, WidgetConfiguration $configuration): ?string;
}
