<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity;

use App\Domain\Import\InvalidActivityFileName;
use App\Domain\Import\UploadActivityFile\CannotUploadActivityFile;
use App\Domain\Import\UploadActivityFile\UploadActivityFile;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Http\Api\ApiErrorResponse;
use App\Infrastructure\Http\Api\CouldNotReadUploadedFilePart;
use App\Infrastructure\Http\Api\UploadedFilePart;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityUploadRequestHandler
{
    private const string PART_NAME = 'file';

    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    #[Route(path: '/api/v1/activity/upload', name: 'api_v1_activity_upload', methods: ['POST', 'PUT'], priority: 3)]
    public function handle(Request $request): Response
    {
        try {
            $filePart = UploadedFilePart::fromRequest($request, self::PART_NAME);
        } catch (CouldNotReadUploadedFilePart $e) {
            return new ApiErrorResponse(
                statusCode: $e->getStatusCode(),
                error: $e->getError(),
                message: $e->getMessage(),
            );
        }

        try {
            $command = UploadActivityFile::fromFile($filePart->getClientOriginalName(), $filePart->getContents());
        } catch (InvalidActivityFileName $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                error: 'unsupported_file_type',
                message: $e->getMessage(),
            );
        }

        try {
            $this->commandBus->dispatch($command);
        } catch (CannotUploadActivityFile $e) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_CONFLICT,
                error: 'import_mode_not_files',
                message: $e->getMessage(),
            );
        }

        return new JsonResponse([
            'status' => 'queued',
            'message' => 'File queued for import. It will be processed within five minutes.',
        ], Response::HTTP_ACCEPTED);
    }
}
