<?php

declare(strict_types=1);

namespace App\Domain\Gear\RecordingDevice\UpdateRecordingDevice;

use App\Domain\Gear\ProvidePurchasePriceFromPayload;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use Money\Money;

final readonly class UpdateRecordingDevice extends DomainCommand implements DeserializableCommand
{
    use ProvidePurchasePriceFromPayload;
    use ProvidesCommandName;

    private function __construct(
        private string $name,
        private ?Money $purchasePrice,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['name']) || !is_string($payload['name'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "name" is required.');
        }

        $name = trim($payload['name']);
        if ('' === $name) {
            throw CouldNotDeserializeCommand::invalidPayload('The name cannot be empty.');
        }

        return new self(
            name: $name,
            purchasePrice: self::parsePurchasePrice($payload),
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPurchasePrice(): ?Money
    {
        return $this->purchasePrice;
    }
}
