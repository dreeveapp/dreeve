<?php

declare(strict_types=1);

namespace App\Domain\Activity\OpenGraph;

use App\Domain\Activity\Activity;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Measurement\ProvideMeasurementFormats;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ActivityOpenGraphImage
{
    use ProvideMeasurementFormats;

    public const int WIDTH = 1200;
    public const int HEIGHT = 630;
    private const int PADDING = 72;
    private const int AVATAR_SIZE = 112;

    public function __construct(
        private SettingsRepository $settingsRepository,
        private TranslatorInterface $translator,
        private Client $client,
        private KernelProjectDir $kernelProjectDir,
    ) {
    }

    public function render(Activity $activity): string
    {
        ob_start();
        imagepng($this->draw($activity));

        return (string) ob_get_clean();
    }

    private function draw(Activity $activity): \GdImage
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($image, 0, 0, $this->color($image, 0xFFFFFF));
        imagefilledrectangle($image, 0, self::HEIGHT - 16, self::WIDTH, self::HEIGHT, $this->color($image, 0xFC5200));

        $dark = $this->color($image, 0x111827);
        $muted = $this->color($image, 0x6B7280);

        $general = $this->settingsRepository->general();
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();

        $athleteX = self::PADDING;
        if (($avatar = $this->avatar((string) $general->getProfilePictureUrl())) instanceof \GdImage) {
            imagecopy($image, $avatar, self::PADDING, self::PADDING, 0, 0, self::AVATAR_SIZE, self::AVATAR_SIZE);
            $athleteX += self::AVATAR_SIZE + 28;
        }
        imagettftext($image, 30, 0, $athleteX, self::PADDING + 70, $dark, $this->font('Bold'), $this->withoutEmoji((string) $general->getAthlete()->getName()));

        $logo = imagecreatefrompng($this->kernelProjectDir.'/public/assets/images/manifest/icon-512.png') ?: throw new \RuntimeException('Could not load the Dreeve logo');
        imagecopyresampled($image, $logo, self::WIDTH - self::PADDING - self::AVATAR_SIZE, self::PADDING, 0, 0, self::AVATAR_SIZE, self::AVATAR_SIZE, imagesx($logo), imagesy($logo));

        imagettftext($image, 24, 0, self::PADDING, 262, $muted, $this->font('Regular'), sprintf(
            '%s · %s',
            $activity->getSportType()->transSingular($this->translator),
            $activity->getStartDate()->translatedFormat('F j, Y'),
        ));

        foreach ($this->wrap($this->withoutEmoji($activity->getName()), 48, $this->font('Bold'), 2) as $i => $line) {
            imagettftext($image, 48, 0, self::PADDING, 336 + $i * 70, $dark, $this->font('Bold'), $line);
        }

        $stats = [
            'Distance' => $this->formatUnitWithSymbol(
                $activity->getDistance()->toUnitSystem($unitSystem),
                $activity->getSportType()->getActivityType()->getDistancePrecision(),
            ),
            'Moving time' => $activity->getMovingTimeFormatted(),
            'Elevation' => $this->formatUnitWithSymbol($activity->getElevation()->toUnitSystem($unitSystem), 0),
        ];
        $columnWidth = intdiv(self::WIDTH - 2 * self::PADDING, count($stats));
        foreach (array_keys($stats) as $i => $label) {
            $x = self::PADDING + $i * $columnWidth;
            imagettftext($image, 18, 0, $x, 486, $muted, $this->font('Regular'), mb_strtoupper($this->translator->trans($label)));
            imagettftext($image, 40, 0, $x, 550, $dark, $this->font('Bold'), $stats[$label]);
        }

        return $image;
    }

    private function avatar(string $url): ?\GdImage
    {
        if ('' === $url) {
            return null;
        }

        try {
            $bytes = (string) $this->client->get($url, ['timeout' => 3])->getBody();
        } catch (GuzzleException) {
            return null;
        }

        if (false === getimagesizefromstring($bytes) || !$source = imagecreatefromstring($bytes)) {
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

    private function withoutEmoji(string $text): string
    {
        return trim((string) preg_replace('/[\p{So}\x{FE0F}\x{200D}\x{10000}-\x{10FFFF}]/u', '', $text));
    }

    private function textWidth(string $text, int $size, string $font): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box ? $box[2] - $box[0] : 0;
    }

    private function font(string $weight): string
    {
        return $this->kernelProjectDir.'/resources/fonts/Inter-'.$weight.'.ttf';
    }

    private function color(\GdImage $image, int $hex): int
    {
        return (int) imagecolorallocate($image, ($hex >> 16) & 0xFF, ($hex >> 8) & 0xFF, $hex & 0xFF);
    }
}
