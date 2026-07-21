<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Settings\Api\ConfigResource;
use App\Domain\Settings\Api\ConfigResourceRegistry;
use App\Domain\Settings\Api\CouldNotResolveConfigResource;
use App\Domain\Settings\Api\WritableConfigResource;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\Http\HttpStatusCode;
use App\Infrastructure\Http\JsonErrorResponse;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Read/write HTTP API over the app's configuration.
 *
 * Endpoints are not enumerated here: every service tagged
 * "app.api.config_resource" is exposed automatically, so adding configuration to
 * the API means adding one ConfigResource implementation and nothing else.
 *
 * Routes need a priority above ApiRequestHandler's catch-all /api/{path}.
 */
#[AsController]
final readonly class ConfigApiRequestHandler
{
    private const int ROUTE_PRIORITY = 5;

    public function __construct(
        private ConfigResourceRegistry $configResourceRegistry,
        private CommandBus $commandBus,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route(path: '/api/v1/config', name: 'api_config_index', methods: ['GET'], priority: self::ROUTE_PRIORITY)]
    public function index(): JsonResponse
    {
        return new JsonResponse([
            'resources' => array_map(
                fn (ConfigResource $configResource): array => [
                    'name' => $configResource->getName(),
                    'href' => $this->hrefFor($configResource->getName()),
                    'methods' => $this->methodsFor($configResource),
                ],
                $this->configResourceRegistry->findAll()
            ),
        ]);
    }

    #[Route(path: '/api/v1/config/{resource}', name: 'api_config_read', requirements: ['resource' => '.+'], methods: ['GET'], priority: self::ROUTE_PRIORITY)]
    public function read(string $resource): Response
    {
        try {
            $configResource = $this->configResourceRegistry->resolve($resource);
        } catch (CouldNotResolveConfigResource $e) {
            return $this->error($e, HttpStatusCode::NOT_FOUND);
        }

        return new JsonResponse($configResource->read());
    }

    #[Route(path: '/api/v1/config/{resource}', name: 'api_config_update', requirements: ['resource' => '.+'], methods: ['PUT'], priority: self::ROUTE_PRIORITY)]
    public function update(string $resource, Request $request): Response
    {
        try {
            $configResource = $this->configResourceRegistry->resolve($resource);
        } catch (CouldNotResolveConfigResource $e) {
            return $this->error($e, HttpStatusCode::NOT_FOUND);
        }

        if (!$configResource instanceof WritableConfigResource) {
            $response = new JsonErrorResponse(
                ['message' => sprintf('Configuration resource "%s" is read-only.', $resource)],
                HttpStatusCode::METHOD_NOT_ALLOWED->value
            );
            $response->headers->set('Allow', 'GET');

            return $response;
        }

        try {
            $payload = Json::decode($request->getContent());
        } catch (\JsonException $e) {
            return $this->error($e, HttpStatusCode::BAD_REQUEST);
        }

        if (!is_array($payload)) {
            return new JsonErrorResponse(['message' => 'Request body must be a JSON object.'], HttpStatusCode::BAD_REQUEST->value);
        }

        try {
            /* @var array<string, mixed> $payload */
            $this->commandBus->dispatch($configResource->buildUpdateCommand($payload));
        } catch (CouldNotDeserializeCommand|CouldNotProcessCommand $e) {
            return $this->error($e, HttpStatusCode::BAD_REQUEST);
        }

        // Echo the stored state back, so a client can confirm what was persisted
        // without a follow-up request.
        return new JsonResponse($configResource->read());
    }

    /**
     * @return list<string>
     */
    private function methodsFor(ConfigResource $configResource): array
    {
        return $configResource instanceof WritableConfigResource ? ['GET', 'PUT'] : ['GET'];
    }

    private function hrefFor(string $resourceName): string
    {
        return $this->urlGenerator->generate('api_config_read', ['resource' => $resourceName]);
    }

    private function error(\Throwable $exception, HttpStatusCode $statusCode): JsonErrorResponse
    {
        return new JsonErrorResponse(['message' => $exception->getMessage()], $statusCode->value);
    }
}
