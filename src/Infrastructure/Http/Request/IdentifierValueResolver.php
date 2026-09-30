<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Request;

use App\Infrastructure\ValueObject\Identifier\Identifier;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsTaggedItem(priority: 150)]
final readonly class IdentifierValueResolver implements ValueResolverInterface
{
    /**
     * @return iterable<Identifier>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();
        if (is_null($type) || !is_subclass_of($type, Identifier::class)) {
            return [];
        }

        $value = $request->attributes->get($argument->getName());
        if (!is_string($value)) {
            return [];
        }

        try {
            return [$type::fromString($value)];
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('Not found');
        }
    }
}
