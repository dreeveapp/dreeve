<?php

declare(strict_types=1);

namespace App\Application\PageMetadata;

use App\Infrastructure\ValueObject\String\KernelProjectDir;

final readonly class OpenGraphImageCanvas
{
    public const int WIDTH = 1200;
    public const int HEIGHT = 630;
    public const int DARK = 0x111827;
    public const int MUTED = 0x6B7280;
    private const int PADDING = 72;
    private const int AVATAR_SIZE = 112;

    private \GdImage $image;

    public function __construct(
        private KernelProjectDir $kernelProjectDir,
    ) {
        $this->image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($this->image, 0, 0, $this->color(0xFFFFFF));
        imagefilledrectangle($this->image, 0, self::HEIGHT - 16, self::WIDTH, self::HEIGHT, $this->color(0xFC5200));
    }

    public function drawHeader(string $athleteName, ?string $profilePicture): void
    {
        $athleteX = self::PADDING;
        if (($avatar = $this->avatar($profilePicture)) instanceof \GdImage) {
            imagecopy($this->image, $avatar, self::PADDING, self::PADDING, 0, 0, self::AVATAR_SIZE, self::AVATAR_SIZE);
            $athleteX += self::AVATAR_SIZE + 28;
        }
        imagettftext($this->image, 30, 0, $athleteX, self::PADDING + 70, $this->color(self::DARK), $this->font('Bold'), $this->withoutEmoji($athleteName));

        $logo = imagecreatefrompng($this->kernelProjectDir.'/public/assets/images/manifest/icon-512.png') ?: throw new \RuntimeException('Could not load the Dreeve logo');
        imagecopyresampled($this->image, $logo, self::WIDTH - self::PADDING - self::AVATAR_SIZE, self::PADDING, 0, 0, self::AVATAR_SIZE, self::AVATAR_SIZE, imagesx($logo), imagesy($logo));
    }

    public function drawText(string $text, int $size, int $y, string $weight, int $color, int $maxLines = 1, int $lineHeight = 0): void
    {
        foreach ($this->wrap($this->withoutEmoji($text), $size, $this->font($weight), $maxLines) as $i => $line) {
            imagettftext($this->image, $size, 0, self::PADDING, $y + $i * $lineHeight, $this->color($color), $this->font($weight), $line);
        }
    }

    /**
     * @param array<string, string> $stats
     */
    public function drawStats(array $stats, int $labelY, int $valueY, int $valueSize): void
    {
        $columnWidth = intdiv(self::WIDTH - 2 * self::PADDING, count($stats));
        foreach (array_keys($stats) as $i => $label) {
            $x = self::PADDING + $i * $columnWidth;
            imagettftext($this->image, 18, 0, $x, $labelY, $this->color(self::MUTED), $this->font('Regular'), mb_strtoupper($label));
            imagettftext($this->image, $valueSize, 0, $x, $valueY, $this->color(self::DARK), $this->font('Bold'), $stats[$label]);
        }
    }

    public function toPng(): string
    {
        ob_start();
        imagepng($this->image);

        return (string) ob_get_clean();
    }

    private function avatar(?string $profilePicture): ?\GdImage
    {
        if (null === $profilePicture || false === getimagesizefromstring($profilePicture) || !$source = imagecreatefromstring($profilePicture)) {
            return null;
        }

        $sourceSize = min(imagesx($source), imagesy($source));
        $avatar = imagecreatetruecolor(self::AVATAR_SIZE, self::AVATAR_SIZE);
        imagealphablending($avatar, false);
        imagesavealpha($avatar, true);
        imagecopyresampled(
            dst_image: $avatar,
            src_image: $source,
            dst_x: 0,
            dst_y: 0,
            src_x: intdiv(imagesx($source) - $sourceSize, 2),
            src_y: intdiv(imagesy($source) - $sourceSize, 2),
            dst_width: self::AVATAR_SIZE,
            dst_height: self::AVATAR_SIZE,
            src_width: $sourceSize,
            src_height: $sourceSize,
        );

        $radius = self::AVATAR_SIZE / 2;
        for ($x = 0; $x < self::AVATAR_SIZE; ++$x) {
            for ($y = 0; $y < self::AVATAR_SIZE; ++$y) {
                $coverage = max(0.0, min(1.0, $radius - hypot($x + 0.5 - $radius, $y + 0.5 - $radius)));
                if ($coverage >= 1.0) {
                    continue;
                }
                $rgb = imagecolorat($avatar, $x, $y);
                imagesetpixel($avatar, $x, $y, (int) imagecolorallocatealpha(
                    image: $avatar,
                    red: ($rgb >> 16) & 0xFF,
                    green: ($rgb >> 8) & 0xFF,
                    blue: $rgb & 0xFF,
                    alpha: max(0, min(127, (int) round(127 * (1 - $coverage)))),
                ));
            }
        }

        return $avatar;
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, int $size, string $font, int $maxLines): array
    {
        $maxWidth = self::WIDTH - 2 * self::PADDING;
        $lines = [''];
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            $last = array_key_last($lines);
            $candidate = ltrim($lines[$last].' '.$word);
            if ('' === $lines[$last] || $this->textWidth($candidate, $size, $font) <= $maxWidth) {
                $lines[$last] = $candidate;
                continue;
            }
            $lines[] = $word;
        }

        $isCut = count($lines) > $maxLines;
        $lines = array_slice($lines, 0, $maxLines);

        return array_map(function (string $line, int $i) use ($isCut, $maxLines, $maxWidth, $size, $font): string {
            if ($this->textWidth($line, $size, $font) <= $maxWidth && (!$isCut || $i !== $maxLines - 1)) {
                return $line;
            }
            while ('' !== $line && $this->textWidth($line.'…', $size, $font) > $maxWidth) {
                $line = mb_substr($line, 0, -1);
            }

            return rtrim($line).'…';
        }, $lines, array_keys($lines));
    }

    private function textWidth(string $text, int $size, string $font): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box ? $box[2] - $box[0] : 0;
    }

    private function withoutEmoji(string $text): string
    {
        return trim((string) preg_replace('/[\p{So}\x{FE0F}\x{200D}\x{10000}-\x{10FFFF}]/u', '', $text));
    }

    private function font(string $weight): string
    {
        return $this->kernelProjectDir.'/resources/fonts/Inter-'.$weight.'.ttf';
    }

    private function color(int $hex): int
    {
        return (int) imagecolorallocate($this->image, ($hex >> 16) & 0xFF, ($hex >> 8) & 0xFF, $hex & 0xFF);
    }
}
