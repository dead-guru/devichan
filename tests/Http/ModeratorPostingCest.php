<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\AdminSession;
use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\HttpTester;

final class ModeratorPostingCest
{
    use AdminSession;
    use HttpAssertions;

    private array $generatedImages = [];

    public function _after(): void
    {
        foreach ($this->generatedImages as $generatedImage) {
            if (is_file($generatedImage)) {
                unlink($generatedImage);
            }
        }
        $this->generatedImages = [];
    }

    public function administratorCanPostRawHtmlWithACapcode(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $body = '<strong>E2E raw capcode post ' . bin2hex(random_bytes(4)) . '</strong>';

        $I->amOnPage('/mod.php?/b/');
        $postButton = $I->grabAttributeFrom(
            'form[name="post"] input[name="post"]',
            'value',
        );
        $I->submitForm('form[name="post"]', [
            'board' => 'b',
            'name' => 'E2E Staff ## Admin',
            'body' => $body,
            'password' => 'e2e-capcode',
            'embed' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'sticky' => 'on',
            'lock' => 'on',
            'raw' => 'on',
            'post' => $postButton,
        ]);
        $this->assertHealthyPage($I);

        $postId = (int) $I->grabFromDatabase('posts_b', 'id', [
            'body_nomarkup' => $body . "\n<tinyboard raw html>1</tinyboard>",
        ]);
        $I->seeInDatabase('posts_b', [
            'id' => $postId,
            'name' => 'E2E Staff',
            'capcode' => 'Admin',
            'sticky' => 1,
            'locked' => 1,
        ]);
        $I->assertStringContainsString(
            '<tinyboard raw html>1</tinyboard>',
            (string) $I->grabFromDatabase('posts_b', 'body_nomarkup', ['id' => $postId]),
        );
    }

    public function administratorCanUploadAJpegImage(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $body = 'E2E JPEG upload ' . bin2hex(random_bytes(4));
        $filename = 'generated-' . bin2hex(random_bytes(4)) . '.jpeg';
        $generatedImage = codecept_data_dir($filename);
        $this->generatedImages[] = $generatedImage;
        $image = file_get_contents('/var/www/static/404/404_4.jpeg');
        $I->assertNotFalse($image);
        file_put_contents($generatedImage, $image . random_bytes(16));

        $I->amOnPage('/mod.php?/b/');
        $I->attachFile(
            'form[name="post"] input[name="file"]',
            $filename,
        );
        $I->fillField('form[name="post"] textarea[name="body"]', $body);
        $I->fillField('form[name="post"] input[name="password"]', 'e2e-jpeg');
        $I->click('form[name="post"] input[name="post"]');
        $this->assertHealthyPage($I);

        $files = json_decode(
            (string) $I->grabFromDatabase('posts_b', 'files', ['body_nomarkup' => $body]),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $I->assertSame('jpeg', $files[0]['extension']);
        $I->assertGreaterThan(0, $files[0]['width']);
        $I->assertGreaterThan(0, $files[0]['height']);
    }

    public function administratorCanUploadImagesWithTheGdBackend(HttpTester $I): void
    {
        $I->setCookie('e2e_thumb_method', 'gd');
        $this->loginAsAdmin($I);

        foreach (['png', 'gif', 'bmp'] as $extension) {
            $filename = 'generated-' . bin2hex(random_bytes(4)) . '.' . $extension;
            $generatedImage = codecept_data_dir($filename);
            $this->generatedImages[] = $generatedImage;

            $image = imagecreatetruecolor(800, 600);
            $color = imagecolorallocate(
                $image,
                random_int(1, 254),
                random_int(1, 254),
                random_int(1, 254),
            );
            imagefill($image, 0, 0, $color);
            $writer = 'image' . $extension;
            $I->assertTrue($writer($image, $generatedImage));
            imagedestroy($image);

            $body = 'E2E GD ' . strtoupper($extension) . ' upload ' . bin2hex(random_bytes(4));
            $I->amOnPage('/mod.php?/b/');
            $I->attachFile('form[name="post"] input[name="file"]', $filename);
            $I->fillField('form[name="post"] textarea[name="body"]', $body);
            $I->fillField('form[name="post"] input[name="password"]', 'e2e-gd-image');
            $I->click('form[name="post"] input[name="post"]');
            $this->assertHealthyPage($I);

            $files = json_decode(
                (string) $I->grabFromDatabase('posts_b', 'files', ['body_nomarkup' => $body]),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            $I->assertSame($extension, $files[0]['extension']);
            $I->assertGreaterThan(0, $files[0]['thumbwidth']);
            $I->assertGreaterThan(0, $files[0]['thumbheight']);
        }
    }

    public function administratorCanCreateAThumbnailWithTheImagickBackend(HttpTester $I): void
    {
        $I->setCookie('e2e_thumb_method', 'imagick');
        $this->loginAsAdmin($I);

        $filename = 'generated-imagick-' . bin2hex(random_bytes(4)) . '.jpeg';
        $generatedImage = codecept_data_dir($filename);
        $this->generatedImages[] = $generatedImage;
        $image = imagecreatetruecolor(800, 600);
        $color = imagecolorallocate($image, 30, 100, random_int(120, 240));
        imagefill($image, 0, 0, $color);
        $I->assertTrue(imagejpeg($image, $generatedImage));
        imagedestroy($image);

        $body = 'E2E Imagick upload ' . bin2hex(random_bytes(4));
        $I->amOnPage('/mod.php?/b/');
        $I->attachFile('form[name="post"] input[name="file"]', $filename);
        $I->fillField('form[name="post"] textarea[name="body"]', $body);
        $I->fillField('form[name="post"] input[name="password"]', 'e2e-imagick');
        $I->click('form[name="post"] input[name="post"]');
        $this->assertHealthyPage($I);

        $files = json_decode(
            (string) $I->grabFromDatabase('posts_b', 'files', ['body_nomarkup' => $body]),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $I->assertSame('jpeg', $files[0]['extension']);
        $I->assertGreaterThan(0, $files[0]['thumbwidth']);
        $I->assertGreaterThan(0, $files[0]['thumbheight']);
    }

    public function administratorCanUploadAWebpImageWithTheGdBackend(HttpTester $I): void
    {
        $I->setCookie('e2e_thumb_method', 'gd');
        $I->setCookie('e2e_allow_webp', '1');
        $this->loginAsAdmin($I);

        $filename = 'generated-' . bin2hex(random_bytes(4)) . '.webp';
        $generatedImage = codecept_data_dir($filename);
        $this->generatedImages[] = $generatedImage;
        $image = imagecreatetruecolor(800, 600);
        $color = imagecolorallocate($image, 120, random_int(40, 200), 70);
        imagefill($image, 0, 0, $color);
        $I->assertTrue(imagewebp($image, $generatedImage));
        imagedestroy($image);

        $body = 'E2E WebP upload ' . bin2hex(random_bytes(4));
        $I->amOnPage('/mod.php?/b/');
        $I->attachFile('form[name="post"] input[name="file"]', $filename);
        $I->fillField('form[name="post"] textarea[name="body"]', $body);
        $I->fillField('form[name="post"] input[name="password"]', 'e2e-webp');
        $I->click('form[name="post"] input[name="post"]');
        $this->assertHealthyPage($I);

        $files = json_decode(
            (string) $I->grabFromDatabase('posts_b', 'files', ['body_nomarkup' => $body]),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $I->assertSame('webp', $files[0]['extension']);
        $I->assertGreaterThan(0, $files[0]['thumbwidth']);
        $I->assertGreaterThan(0, $files[0]['thumbheight']);
    }

    public function administratorCanUploadAnImageFromAUrl(HttpTester $I): void
    {
        $I->setCookie('e2e_upload_by_url', '1');
        $this->loginAsAdmin($I);

        $filename = 'url-upload-' . bin2hex(random_bytes(4)) . '.jpeg';
        $generatedImage = codecept_data_dir($filename);
        $this->generatedImages[] = $generatedImage;
        $image = imagecreatetruecolor(800, 600);
        $color = imagecolorallocate($image, 80, 120, random_int(1, 254));
        imagefill($image, 0, 0, $color);
        $I->assertTrue(imagejpeg($image, $generatedImage));
        imagedestroy($image);

        $body = 'E2E URL image upload ' . bin2hex(random_bytes(4));
        $I->amOnPage('/mod.php?/b/');
        $I->fillField(
            'form[name="post"] input[name="file_url"]',
            'http://caddy/tests/Support/Data/' . $filename,
        );
        $I->fillField('form[name="post"] textarea[name="body"]', $body);
        $I->fillField('form[name="post"] input[name="password"]', 'e2e-url-image');
        $I->click('form[name="post"] input[name="post"]');
        $this->assertHealthyPage($I);

        $postId = (int) $I->grabFromDatabase('posts_b', 'id', ['body_nomarkup' => $body]);
        $files = json_decode(
            (string) $I->grabFromDatabase('posts_b', 'files', ['id' => $postId]),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $I->assertSame($filename, $files[0]['filename']);
        $I->assertSame('jpeg', $files[0]['extension']);

        $I->amOnPage('/mod.php?/b/res/' . $postId . '.html');
        $I->assertSame(1, preg_match(
            "/document\\.location='([^']*b\\/delete\\/{$postId}\\/[a-f0-9]{8})'/",
            $I->grabPageSource(),
            $matches,
        ));
        $I->amOnPage($matches[1]);
        $I->dontSeeInDatabase('posts_b', ['id' => $postId]);
    }
}
