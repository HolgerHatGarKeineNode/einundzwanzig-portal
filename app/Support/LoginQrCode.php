<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Eye\ModuleEye;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Module\SquareModule;
use BaconQrCode\Renderer\RendererStyle\EyeFill;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use InvalidArgumentException;

/**
 * The LNURL login QR code: 300 px PNG, error correction H, the domain image
 * merged into the centre.
 *
 * Replaces simplesoftwareio/simple-qrcode, which pinned bacon/bacon-qr-code ^2
 * and declares an implicitly nullable parameter that PHP 8.4 deprecates. The
 * rendering and the merge reproduce that package's `format('png')->size(300)
 * ->merge($image, .3)->errorCorrection('H')` byte for byte.
 */
class LoginQrCode
{
    private const int SIZE = 300;

    public static function png(string $content, string $mergeImagePath, float $mergePercentage = .3): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(
                self::SIZE,
                0,
                SquareModule::instance(),
                new ModuleEye(SquareModule::instance()),
                Fill::withForegroundColor(new Rgb(255, 255, 255), new Rgb(0, 0, 0), EyeFill::inherit(), EyeFill::inherit(), EyeFill::inherit()),
            ),
            new ImagickImageBackEnd('png'),
        );

        $qrCode = (new Writer($renderer))->writeString($content, 'ISO-8859-1', ErrorCorrectionLevel::H());

        return self::merge($qrCode, (string) file_get_contents($mergeImagePath), $mergePercentage);
    }

    private static function merge(string $qrCode, string $mergeImage, float $percentage): string
    {
        if ($percentage > 1) {
            throw new InvalidArgumentException('$percentage must be less than 1');
        }

        $source = imagecreatefromstring($qrCode);
        $overlay = imagecreatefromstring($mergeImage);

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $overlayWidth = imagesx($overlay);
        $overlayHeight = imagesy($overlay);

        $ratio = round($overlayWidth / $overlayHeight, 2);
        $mergedWidth = intval($sourceWidth * $percentage);
        $mergedHeight = intval($mergedWidth / $ratio);

        $image = imagecreatetruecolor($sourceWidth, $sourceHeight);
        imagealphablending($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagecopy($image, $source, 0, 0, 0, 0, $sourceWidth, $sourceHeight);
        imagecopyresampled(
            $image,
            $overlay,
            intval(($sourceWidth / 2) - ($mergedWidth / 2)),
            intval(($sourceHeight / 2) - ($mergedHeight / 2)),
            0,
            0,
            $mergedWidth,
            $mergedHeight,
            $overlayWidth,
            $overlayHeight,
        );

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
