<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser;

final readonly class Xml
{
    public static function load(string $contents): ?\SimpleXMLElement
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }
        $contents = ltrim($contents);

        // Strip namespace declarations and prefixes so SimpleXML element access is uniform
        // regardless of the file's namespaces.
        $contents = (string) preg_replace('/xmlns(:\w+)?="[^"]*"/', '', $contents);
        $contents = (string) preg_replace('/(<\/?)\w+:/', '$1', $contents);

        $previousErrorState = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents);
        libxml_use_internal_errors($previousErrorState);

        return false !== $xml ? $xml : null;
    }
}
