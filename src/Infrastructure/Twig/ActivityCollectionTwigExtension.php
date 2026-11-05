<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Domain\Activity\Collection\FindTripSuggestions\FindTripSuggestions;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use Twig\Attribute\AsTwigFunction;

final readonly class ActivityCollectionTwigExtension
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    #[AsTwigFunction('number_of_trip_suggestions')]
    public function numberOfTripSuggestions(): int
    {
        return count($this->queryBus->ask(new FindTripSuggestions())->getTripSuggestions());
    }
}
