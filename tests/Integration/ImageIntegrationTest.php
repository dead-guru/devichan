<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PHPUnit\Framework\TestCase;

final class ImageIntegrationTest extends TestCase
{
    private array $originalConfig;
    private string $directory = 'tests/_output/integration-images';

    protected function setUp(): void
    {
        global $config;

        $this->originalConfig = $config;
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
        $config['strip_exif'] = false;
        $config['thumb_ext'] = '';
        $config['thumb_keep_animation_frames'] = 1;
    }

    protected function tearDown(): void
    {
        global $config;
        $config = $this->originalConfig;
    }

    public function testGdImagesReadResizeAndWriteEverySupportedFormat(): void
    {
        global $config;

        $formats = ['png', 'gif', 'jpg', 'webp', 'bmp'];
        foreach ($formats as $format) {
            $source = $this->directory . '/source.' . $format;
            $this->createGdImage($source, $format, 120, 60);
            $config['thumb_method'] = 'gd';

            $image = new \Image($source, $format);
            self::assertSame(120, $image->size->width);
            self::assertSame(60, $image->size->height);
            $thumb = $image->resize($format, 40, 40);
            $target = $this->directory . '/thumb-' . $format . '.' . $format;
            $thumb->to($target);
            self::assertFileExists($target);
            self::assertSame(40, $thumb->_width());
            self::assertSame(20, $thumb->_height());
            $thumb->_destroy();
            $image->destroy();
        }

        $tall = $this->directory . '/tall.png';
        $this->createGdImage($tall, 'png', 60, 120);
        $image = new \Image($tall, 'png');
        $thumb = $image->resize('png', 40, 40);
        self::assertSame(20, $thumb->_width());
        self::assertSame(40, $thumb->_height());
        $thumb->_destroy();
        $image->destroy();
    }

    public function testImagickAndConvertBackendsResizeWriteAndOrientImages(): void
    {
        global $config;

        $source = $this->directory . '/imagick.jpg';
        $this->createGdImage($source, 'jpg', 100, 50);

        $config['thumb_method'] = 'imagick';
        $imagick = new \Image($source, 'jpg');
        $imagickThumb = $imagick->resize('jpg', 50, 50);
        $imagickTarget = $this->directory . '/imagick-thumb.jpg';
        $imagickThumb->to($imagickTarget);
        self::assertFileExists($imagickTarget);
        $imagickThumb->_destroy();
        $config['strip_exif'] = true;
        $imagick->to($this->directory . '/imagick-stripped.jpg');
        $imagick->destroy();

        $config['thumb_method'] = 'convert';
        $config['strip_exif'] = true;
        $convert = new \Image($source, 'jpg');
        $convertThumb = $convert->resize('jpg', 25, 25);
        $convertTarget = $this->directory . '/convert-thumb.jpg';
        $convertThumb->to($convertTarget);
        self::assertFileExists($convertTarget);
        self::assertSame(25, $convertThumb->width());
        self::assertSame(13, $convertThumb->height());
        $convertThumb->destroy();

        $expected = [
            1 => false,
            2 => '-flop',
            3 => '-flip -flop',
            4 => '-flip',
            5 => '-rotate 90 -flop',
            6 => '-rotate 90',
            7 => '-rotate "-90" -flop',
            8 => '-rotate "-90"',
        ];
        foreach ($expected as $orientation => $command) {
            self::assertSame($command, \ImageConvert::jpeg_exif_orientation($source, ['Orientation' => $orientation]));
        }
        self::assertFalse(\ImageConvert::jpeg_exif_orientation($source));
    }

    public function testImageFacadeWritesAndDeletesTheOriginalImage(): void
    {
        global $config;

        $source = $this->directory . '/facade-source.png';
        $target = $this->directory . '/facade-target.png';
        $this->createGdImage($source, 'png', 32, 24);
        $config['thumb_method'] = 'gd';

        $image = new \Image($source, 'png');
        $image->to($target);
        self::assertFileExists($target);
        $image->delete();
        self::assertFileDoesNotExist($source);
        $image->destroy();
    }

    public function testConvertBackendsExerciseRedrawOrientationAndTemporaryFilePaths(): void
    {
        global $config;

        $jpeg = $this->directory . '/convert-source.jpg';
        $png = $this->directory . '/convert-source.png';
        $this->createGdImage($jpeg, 'jpg', 96, 48);
        $this->createGdImage($png, 'png', 48, 96);
        foreach (['convert', 'gm'] as $method) {
            $config['thumb_method'] = $method;
            foreach ([$jpeg => 'jpg', $png => 'png'] as $source => $format) {
                $config['convert_manual_orient'] = $format === 'png';
                $image = new \Image($source, $format);

                foreach ([false, true] as $stripExif) {
                    $config['strip_exif'] = $stripExif;
                    $redrawn = $this->directory . "/{$method}-redrawn-{$format}-" . (int) $stripExif . ".{$format}";
                    $image->to($redrawn);
                    self::assertFileExists($redrawn);
                }

                $thumb = $image->resize($format, 24, 24);
                $target = $this->directory . "/{$method}-manual-orient.{$format}";
                $thumb->to($target);
                self::assertFileExists($target);
                $thumb->destroy();
                $image->destroy();
            }
        }
    }

    public function testAnimatedGifResizeUsesImagickConvertAndGifsicleBranches(): void
    {
        global $config;

        $source = $this->directory . '/animated.gif';
        $this->createAnimatedGif($source);
        $config['thumb_ext'] = 'gif';
        $config['thumb_keep_animation_frames'] = 2;

        foreach (['imagick', 'convert', 'convert+gifsicle', 'gm', 'gm+gifsicle'] as $method) {
            $config['thumb_method'] = $method;
            $image = new \Image($source, 'gif');
            $thumb = $image->resize('gif', 24, 24);
            $target = $this->directory . "/animated-{$method}.gif";
            $thumb->to($target);
            self::assertFileExists($target);
            self::assertGreaterThan(0, filesize($target));
            $thumb->destroy();
            $image->destroy();
        }
    }

    private function createGdImage(string $path, string $format, int $width, int $height): void
    {
        $resource = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($resource, 30, 80, 160);
        imagefilledrectangle($resource, 0, 0, $width, $height, $background);

        match ($format) {
            'png' => imagepng($resource, $path),
            'gif' => imagegif($resource, $path),
            'jpg' => imagejpeg($resource, $path),
            'webp' => imagewebp($resource, $path),
            'bmp' => imagebmp($resource, $path),
        };
        imagedestroy($resource);
    }

    private function createAnimatedGif(string $path): void
    {
        $animation = new \Imagick();
        $animation->setFormat('gif');

        foreach (['red', 'green', 'blue', 'yellow'] as $color) {
            $frame = new \Imagick();
            $frame->newImage(64, 32, new \ImagickPixel($color));
            $frame->setImageFormat('gif');
            $frame->setImageDelay(5);
            $animation->addImage($frame);
            $frame->destroy();
        }

        $animation->writeImages($path, true);
        $animation->destroy();
    }
}
