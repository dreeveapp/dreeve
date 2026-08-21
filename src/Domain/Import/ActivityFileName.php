<?php

declare(strict_types=1);

namespace App\Domain\Import;

final readonly class ActivityFileName implements \Stringable
{
    private const int MAX_LENGTH_IN_BYTES = 200;

    private function __construct(
        private string $name,
        private SupportedFileExtension $extension,
    ) {
    }

    public static function fromString(string $name): self
    {
        if (!mb_check_encoding($name, 'UTF-8') || 1 === preg_match('#[\x00-\x1F\x7F]#', $name)) {
            throw InvalidActivityFileName::isNotValid();
        }

        $name = basename(str_replace('\\', '/', $name));

        if (strlen($name) > self::MAX_LENGTH_IN_BYTES) {
            throw InvalidActivityFileName::isTooLong(self::MAX_LENGTH_IN_BYTES);
        }

        if ('' === pathinfo($name, PATHINFO_FILENAME)) {
            throw InvalidActivityFileName::isNotValid();
        }

        $extension = SupportedFileExtension::tryFrom(strtolower(pathinfo($name, PATHINFO_EXTENSION)))
            ?? throw InvalidActivityFileName::fileTypeIsNotSupported();

        return new self($name, $extension);
    }

    public function getExtension(): SupportedFileExtension
    {
        return $this->extension;
    }

    public function withSuffix(string $suffix): self
    {
        return new self(
            sprintf(
                '%s-%s.%s',
                pathinfo($this->name, PATHINFO_FILENAME),
                $suffix,
                pathinfo($this->name, PATHINFO_EXTENSION),
            ),
            $this->extension,
        );
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
