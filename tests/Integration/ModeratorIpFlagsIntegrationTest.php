<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use DeVichan\Moderator\IpFlags;
use PHPUnit\Framework\TestCase;

final class ModeratorIpFlagsIntegrationTest extends TestCase
{
    private array $originalConfig;
    private mixed $originalMod;

    protected function setUp(): void
    {
        global $config, $mod;

        $this->originalConfig = $config;
        $this->originalMod = $mod;
        $config['geoip_database'] = 'tests/fixtures/geoip/GeoIP2-Country-Test.mmdb';
        $config['mod']['country_flags'] = true;
        $config['country_flags'] = false;
        $config['ipcrypt_key'] = 'moderator-flag-test';
        $config['ipcrypt_prefix'] = 'test';
        $config['ipcrypt_dns'] = false;
        $mod = ['id' => 1, 'type' => ADMIN, 'username' => 'admin', 'boards' => ['*']];
    }

    protected function tearDown(): void
    {
        global $config, $mod;

        $config = $this->originalConfig;
        $mod = $this->originalMod;
    }

    public function testCountryFlagsRequireModeratorAndFeatureAndDoNotExposeAddresses(): void
    {
        global $config, $mod;

        $flag = IpFlags::render('81.2.69.142');
        self::assertStringContainsString('static/flags/gb.png', $flag);
        self::assertStringContainsString('title="United Kingdom"', $flag);
        self::assertStringNotContainsString('81.2.69.142', $flag);
        self::assertStringContainsString('static/flags/jp.png', IpFlags::render('2001:218::'));
        self::assertSame($flag, IpFlags::render('81.2.69.142/32'));
        self::assertNotSame('', IpFlags::render('2001:218::/128'));
        foreach (['81.2.69.0/24', '2001:218::/64', '127.0.0.1', '8.8.8.8', 'invalid'] as $ip) {
            self::assertSame('', IpFlags::render($ip), $ip);
        }

        $config['mod']['country_flags'] = false;
        self::assertSame('', IpFlags::render('81.2.69.142'));
        $config['mod']['country_flags'] = true;
        $mod = false;
        self::assertSame('', IpFlags::render('81.2.69.142'));
    }

    public function testPostFlagFollowsTheExistingIpPermission(): void
    {
        global $config, $mod;

        $post = ['ip' => '81.2.69.142', 'mod' => $mod];
        $args = ['config' => $config, 'board' => ['uri' => 'b'], 'post' => $post];
        $html = Element('post/ip.html', $args);
        self::assertStringContainsString('mod-ip-flag', $html);
        self::assertStringContainsString('href="?/IP/' . cloak_ip($post['ip']) . '"', $html);
        self::assertStringNotContainsString($post['ip'], $html);

        $args['post']['mod'] = false;
        self::assertStringNotContainsString('mod-ip-flag', Element('post/ip.html', $args));
        $args['post']['mod'] = ['id' => 2, 'type' => JANITOR, 'boards' => ['b']];
        self::assertStringNotContainsString('mod-ip-flag', Element('post/ip.html', $args));
        $args['post']['mod'] = ['id' => 2, 'type' => MOD, 'boards' => ['sec']];
        self::assertStringNotContainsString('mod-ip-flag', Element('post/ip.html', $args));
    }

    public function testHistoricalLogFlagsLeaveLinksAndAttributesIntactAndStayPrivate(): void
    {
        global $mod;

        $cloak = cloak_ip('81.2.69.142');
        $html = 'Removed <a href="?/IP/' . $cloak . '" title="' . $cloak . '">' . $cloak . '</a>'
            . ' <span data-ip="' . $cloak . '">Accepted ' . $cloak . '</span>'
            . '<script>var ip = "' . $cloak . '";</script>';
        $rendered = IpFlags::decorate($html);
        self::assertSame(2, substr_count($rendered, 'mod-ip-flag'));
        self::assertStringContainsString('href="?/IP/' . $cloak . '"', $rendered);
        self::assertStringContainsString('title="' . $cloak . '"', $rendered);
        self::assertStringContainsString('data-ip="' . $cloak . '"', $rendered);
        self::assertStringContainsString('<script>var ip = "' . $cloak . '";</script>', $rendered);
        self::assertStringNotContainsString('81.2.69.142', $rendered);
        self::assertSame($html, IpFlags::decorate($html, true));
        self::assertSame('Unknown test:AAAA', IpFlags::decorate('Unknown test:AAAA'));
        $invalid = '<a href="?/IP/test:">test:</a>';
        self::assertSame($invalid, IpFlags::decorate($invalid));
        $mod = false;
        self::assertSame($html, IpFlags::decorate($html));
    }

    public function testPublicLogDoesNotGainFlagsEvenWithAModeratorSession(): void
    {
        global $config, $mod;

        $cloak = cloak_ip('81.2.69.142');
        $args = [
            'config' => $config,
            'mod' => $mod,
            'public' => false,
            'logs' => [[
                'username' => 'admin', 'mod' => 1, 'ip' => '81.2.69.142',
                'time' => time(), 'board' => 'b', 'text' => 'Accepted ' . $cloak,
            ]],
            'count' => 1,
            'page' => 1,
        ];
        self::assertSame(2, substr_count(Element('mod/log.html', $args), 'mod-ip-flag'));
        $args['public'] = true;
        self::assertStringNotContainsString('mod-ip-flag', Element('mod/log.html', $args));
    }

    public function testBanFormAndTableKeepValuesAndHiddenIpBehavior(): void
    {
        global $config, $mod;

        $args = ['config' => $config, 'mod' => $mod, 'ip' => '81.2.69.142'];
        $html = Element('mod/ban_form.html', $args);
        self::assertStringContainsString('value="' . cloak_ip($args['ip']) . '"', $html);
        self::assertStringContainsString('mod-ip-flag', $html);
        $args['hide_ip'] = true;
        self::assertStringNotContainsString('mod-ip-flag', Element('mod/ban_form.html', $args));

        $args = ['config' => $config, 'mod' => $mod, 'bans' => [[
            'mask' => '81.2.69.142', 'created' => time(), 'expires' => 0,
        ]]];
        self::assertStringContainsString('mod-ip-flag', Element('mod/bans_table.html', $args));
        $args['show_ip'] = false;
        self::assertStringNotContainsString('mod-ip-flag', Element('mod/bans_table.html', $args));
    }

    public function testGeoLiteAttributionFollowsPublicAndModeratorFlags(): void
    {
        global $config, $mod;

        self::assertStringContainsString('GeoLite Data', Element('footer.html', ['config' => $config, 'mod' => $mod]));
        self::assertStringNotContainsString('GeoLite Data', Element('footer.html', ['config' => $config, 'mod' => false]));
        $config['country_flags'] = true;
        self::assertStringContainsString('GeoLite Data', Element('footer.html', ['config' => $config, 'mod' => false]));
    }

    public function testBanListAssetsResolveFromNestedModeratorRoutes(): void
    {
        global $config, $mod;

        $config['root'] = '/board/';
        $html = Element('mod/ban_list.html', ['config' => $config, 'mod' => $mod, 'boards' => '["*"]', 'token_json' => 'test']);
        foreach (['main.js', 'js/jquery.min.js', 'js/mobile-style.js', 'js/strftime.min.js', 'js/mod/ban-list.js'] as $path) {
            self::assertStringContainsString("src='/board/" . $path . "'", $html);
        }
        self::assertStringContainsString("href='/board/stylesheets/mod/ban-list.css'", $html);
    }
}
