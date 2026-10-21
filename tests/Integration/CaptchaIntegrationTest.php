<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PHPUnit\Framework\TestCase;

final class CaptchaIntegrationTest extends TestCase
{
    public function testSixCharacterEndpointChallengesProducePngAtExpectedSize(): void
    {
        require_once 'inc/captcha/captcha.php';

        foreach (['WWWWWW', 'MMMMMM', 'GHR279'] as $text) {
            $captcha = new \CzaksCaptcha($text, 215, 80);
            $image = new \Imagick();
            $image->readImageBlob(base64_decode($captcha->to_image(), true));
            self::assertSame(215, $image->getImageWidth());
            self::assertSame(80, $image->getImageHeight());
            self::assertSame('PNG', $image->getImageFormat());
        }
    }

    public function testCaptchaProducesHtmlAndPngAndCleansExpiredChallenges(): void
    {
        global $pdo;

        \sql_open();
        $root = getcwd();
        chdir('inc/captcha');
        require_once 'captcha.php';

        $captcha = new \CzaksCaptcha('abc123', 250, 80, 'abc123');
        $encoded = $captcha->to_image();
        self::assertNotSame('', $encoded);
        self::assertStringStartsWith("\x89PNG", (string) base64_decode($encoded, true));
        self::assertStringContainsString('data:image/jpg;base64,', $captcha->to_html());

        $_GET = [];
        require_once 'entrypoint.php';
        self::assertSame(12, mb_strlen(\rand_string(12, 'abcdef'), 'UTF-8'));

        $pdo->exec("INSERT INTO `captchas` (`cookie`, `extra`, `text`, `created_at`) VALUES ('expired-e2e', 'abc', 'one', 1)");
        \cleanup($pdo, 120);
        $query = $pdo->query("SELECT COUNT(*) FROM `captchas` WHERE `cookie` = 'expired-e2e'");
        self::assertSame(0, $query->fetchColumn());
        chdir($root);
    }
}
