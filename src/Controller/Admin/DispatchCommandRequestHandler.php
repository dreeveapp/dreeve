<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\CQRS\Command\Deserialize\CommandDeserializer;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\SuppressesFlashMessage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsController]
final readonly class DispatchCommandRequestHandler
{
    public const string CSRF_TOKEN_ID = 'dispatch-command';
    public const string CSRF_TOKEN_HEADER = 'X-CSRF-TOKEN';

    public function __construct(
        private CommandDeserializer $commandDeserializer,
        private CommandBus $commandBus,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/admin/dispatchCommand', name: 'admin_dispatch_command', methods: ['POST'], priority: 10)]
    public function handle(Request $request): JsonResponse
    {
        $token = new CsrfToken(self::CSRF_TOKEN_ID, $request->headers->get(self::CSRF_TOKEN_HEADER, ''));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            return new JsonResponse(['error' => $this->translator->trans('Invalid CSRF token.', [], 'admin')], Response::HTTP_FORBIDDEN);
        }

        try {
            $command = $this->commandDeserializer->deserialize($request->getContent());
        } catch (CouldNotDeserializeCommand $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->commandBus->dispatch($command);
        } catch (CouldNotProcessCommand $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $suppressesFlash = [] !== new \ReflectionClass($command)->getAttributes(SuppressesFlashMessage::class);

        $session = $request->getSession();
        if (!$suppressesFlash && $session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(
                type: 'success',
                message: $this->translator->trans('Your changes have been saved.', [], 'admin')
            );
        }

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }
}
