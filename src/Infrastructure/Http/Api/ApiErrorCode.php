<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

enum ApiErrorCode: string
{
    case INVALID_TOKEN = 'invalid_token';
    case MISSING_FILE = 'missing_file';
    case UNSUPPORTED_MEDIA_TYPE = 'unsupported_media_type';
    case UNSUPPORTED_FILE_TYPE = 'unsupported_file_type';
    case FILE_TOO_LARGE = 'file_too_large';
    case IMPORT_MODE_NOT_FILES = 'import_mode_not_files';
    case FILE_NOT_AVAILABLE = 'file_not_available';
    case NOT_FOUND = 'not_found';
    case METHOD_NOT_ALLOWED = 'method_not_allowed';
    case BAD_REQUEST = 'bad_request';
    case INTERNAL_ERROR = 'internal_error';
}
