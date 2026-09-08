<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class AthleteWeightRequest
{
    public function __construct(
        #[Assert\Positive(message: '"weight" must be a positive number.')]
        public float $weight,
        #[Assert\NotBlank(message: '"on" is required.')]
        public string $on,
    ) {
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (!SerializableDateTime::isValidDateString($this->on)) {
            $context->buildViolation('"on" must be a date in YYYY-MM-DD format.')
                ->atPath('on')
                ->addViolation();
        }
    }
}
