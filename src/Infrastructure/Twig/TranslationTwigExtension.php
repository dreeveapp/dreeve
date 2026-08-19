<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Localisation\TranslatableWithDescription;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFilter;

final readonly class TranslationTwigExtension
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, string> $arguments
     */
    #[AsTwigFilter('transDescription')]
    public function transDescription(TranslatableWithDescription $message, array $arguments = [], ?string $domain = null): string
    {
        if ($message instanceof TranslatableMessage && '' === $message->getMessage()) {
            return '';
        }

        return $message->transDescription(
            translator: $this->translator,
            parameters: $arguments,
            domain: $domain
        );
    }

    #[AsTwigFilter('transSingular')]
    public function transSingular(SportType $message): string
    {
        return $message->transSingular($this->translator);
    }
}
