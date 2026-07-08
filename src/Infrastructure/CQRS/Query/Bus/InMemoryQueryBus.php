<?php

declare(strict_types=1);

namespace App\Infrastructure\CQRS\Query\Bus;

use App\Infrastructure\CQRS\HandlerBuilder;
use App\Infrastructure\CQRS\HandlerBuilderType;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class InMemoryQueryBus implements QueryBus
{
    private MessageBusInterface $bus;

    /**
     * @param iterable<QueryHandler> $queryHandlers
     */
    public function __construct(
        #[AutowireIterator('app.query_handler')]
        iterable $queryHandlers,
    ) {
        $this->bus = new MessageBus([
            new HandleMessageMiddleware(
                new HandlersLocator(
                    new HandlerBuilder(HandlerBuilderType::QUERY_HANDLER)
                        ->fromCallables($queryHandlers),
                ),
            ),
        ]);
    }

    public function ask(Query $query): Response
    {
        try {
            /** @var HandledStamp $stamp */
            $stamp = $this->bus->dispatch($query)->last(HandledStamp::class);

            return $stamp->getResult();
        } catch (NoHandlerForMessageException) {
            throw new \InvalidArgumentException(sprintf('The query has not a valid handler: %s', $query::class));
        } catch (HandlerFailedException $e) {
            if (!is_null($e->getPrevious())) {
                throw $e->getPrevious();
            }
            throw $e;
        }
    }
}
