<?php

declare(strict_types=1);

namespace App\Infrastructure\Daemon\Cron;

use App\Domain\Import\ImportMode;
use Cron\CronExpression;

final readonly class CronAction
{
    private function __construct(
        private CronActionId $id,
        private CronExpression $expression,
    ) {
    }

    public static function create(
        CronActionId $id,
        CronExpression $expression,
    ): self {
        return new self(
            id: $id,
            expression: $expression,
        );
    }

    public function getId(): CronActionId
    {
        return $this->id;
    }

    public function getExpression(): CronExpression
    {
        return $this->expression;
    }

    public function getCommand(): string
    {
        return $this->id->command();
    }

    public function supportsImportMode(ImportMode $importMode): bool
    {
        return $this->id->supportsImportMode($importMode);
    }
}
