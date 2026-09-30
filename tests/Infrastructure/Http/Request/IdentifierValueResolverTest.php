<?php

namespace App\Tests\Infrastructure\Http\Request;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\Http\Request\IdentifierValueResolver;
use App\Tests\ContainerTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IdentifierValueResolverTest extends ContainerTestCase
{
    public function testItResolvesAPrefixedIdentifier(): void
    {
        $this->assertEquals(
            [ActivityId::fromString('activity-123')],
            new IdentifierValueResolver()->resolve(
                new Request(attributes: ['activityId' => 'activity-123']),
                new ArgumentMetadata('activityId', ActivityId::class, false, false, null),
            ),
        );
    }

    public function testItThrowsANotFoundForAnIdentifierWithoutItsPrefix(): void
    {
        $this->expectExceptionObject(new NotFoundHttpException('Not found'));

        new IdentifierValueResolver()->resolve(
            new Request(attributes: ['activityId' => '123']),
            new ArgumentMetadata('activityId', ActivityId::class, false, false, null),
        );
    }

    public function testItIgnoresArgumentsThatAreNoIdentifier(): void
    {
        $this->assertEquals(
            [],
            new IdentifierValueResolver()->resolve(
                new Request(attributes: ['activityId' => 'activity-123']),
                new ArgumentMetadata('activityId', 'string', false, false, null),
            ),
        );
    }

    public function testItIgnoresAMissingAttribute(): void
    {
        $this->assertEquals(
            [],
            new IdentifierValueResolver()->resolve(
                new Request(),
                new ArgumentMetadata('activityId', ActivityId::class, false, false, null),
            ),
        );
    }

    public function testItRunsBeforeTheRawRequestAttributeResolver(): void
    {
        /** @var ArgumentResolverInterface $argumentResolver */
        $argumentResolver = $this->getContainer()->get('argument_resolver');

        $this->assertEquals(
            [ActivityId::fromString('activity-123')],
            $argumentResolver->getArguments(
                new Request(attributes: ['activityId' => 'activity-123']),
                new IdentifierControllerStub()->handle(...),
            ),
        );
    }
}
