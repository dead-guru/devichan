<?php

declare(strict_types=1);

namespace DevichanE2E\Installer;

use DevichanE2E\Support\HttpAssertions;
use DevichanE2E\Support\InstallerTester;
use PDO;

final class FreshInstallCest
{
    use HttpAssertions;

    private PDO $pdo;

    public function _before(): void
    {
        $this->pdo = new PDO(
            'mysql:host=cmysql-installer;dbname=devichan_installer_e2e;charset=utf8mb4',
            'devichan_installer_e2e',
            'devichan_installer_e2e',
        );
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $escapedTable = str_replace('`', '``', (string) $table);
            $this->pdo->exec('DROP TABLE `' . $escapedTable . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $marker = '/var/www/tests/_output/installer.installed';
        if (file_exists($marker)) {
            unlink($marker);
        }
    }

    public function rendersAgreementRequirementsConfigurationAndManualConfig(InstallerTester $I): void
    {
        $routes = [
            '/install.php' => 'Proceed to installation',
            '/install.php?step=1' => 'Checking environment',
            '/install.php?step=2' => 'Configuration',
        ];

        foreach ($routes as $route => $expectedText) {
            $I->amOnPage($route);
            $this->assertHealthyPage($I);
            $I->see($expectedText);
        }

        $I->submitForm('form[action="?step=3"]', [
            'db[password]' => 'devichan_installer_e2e',
        ]);
        $this->assertHealthyPage($I);
        $I->see('Manual installation required');
        $I->see('Please complete the installation manually');
    }

    public function createsTheDatabaseAndInitialBoard(InstallerTester $I): void
    {
        $this->installFreshSite($I);

        $marker = '/var/www/tests/_output/installer.installed';
        $siteDirectory = '/var/www/tests/_output/installer-site';
        $I->assertSame('5.1.4', trim((string) file_get_contents($marker)));
        $I->assertSame(['b'], $this->pdo->query('SELECT `uri` FROM `boards`')->fetchAll(PDO::FETCH_COLUMN));
        $I->assertFileExists($siteDirectory . '/main.js');
        $I->assertFileExists($siteDirectory . '/b/index.html');
        $I->assertFileExists($siteDirectory . '/b/0.json');
    }

    public function reportsAlreadyInstalledAndUnknownVersions(InstallerTester $I): void
    {
        $this->installFreshSite($I);

        $I->amOnPage('/install.php');
        $this->assertHealthyPage($I);
        $I->see('Already installed');

        file_put_contents(
            '/var/www/tests/_output/installer.installed',
            'e2e-unknown-version',
        );
        $I->amOnPage('/install.php');
        $this->assertHealthyPage($I);
        $I->see('Unknown version');
    }

    public function pausesLegacyUpgradeForRequiredConfirmations(InstallerTester $I): void
    {
        $this->installFreshSite($I);
        $marker = '/var/www/tests/_output/installer.installed';

        file_put_contents($marker, '4.4.97');
        $I->amOnPage('/install.php');
        $this->assertHealthyPage($I);
        $I->see('License Change');
        $I->see('Proceed to upgrading');

        file_put_contents($marker, '4.5.2');
        $I->amOnPage('/install.php');
        $this->assertHealthyPage($I);
        $I->see('Breaking change');
        $I->see('back up your database');
    }

    public function upgradesA490DatabaseToTheCurrentSchema(InstallerTester $I): void
    {
        $this->installFreshSite($I);
        $this->pdo->exec('ALTER TABLE `posts_b` DROP COLUMN `slug`, DROP COLUMN `cycle`');
        $this->pdo->exec('ALTER TABLE `mods` CHANGE `version` `salt` VARCHAR(64) NOT NULL');
        $this->pdo->exec('DROP TABLE `pages`, `captchas`, `search_queries`');
        file_put_contents(
            '/var/www/tests/_output/installer.installed',
            '4.9.90',
        );

        $I->amOnPage('/install.php');
        $this->assertHealthyPage($I);
        $I->see('Successfully upgraded from 4.9.90');
        $I->assertSame(
            '5.1.4',
            trim((string) file_get_contents('/var/www/tests/_output/installer.installed')),
        );
        $I->assertSame(
            ['cycle', 'slug'],
            $this->pdo->query(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'posts_b'
                   AND COLUMN_NAME IN ('cycle', 'slug')
                 ORDER BY COLUMN_NAME",
            )->fetchAll(PDO::FETCH_COLUMN),
        );
        foreach (['captchas', 'pages', 'search_queries'] as $table) {
            $I->assertSame(
                $table,
                $this->pdo->query("SHOW TABLES LIKE '{$table}'")->fetchColumn(),
            );
        }
    }

    public function upgradesA452MultiImageDatabaseToTheCurrentSchema(InstallerTester $I): void
    {
        $this->installFreshSite($I);
        $this->prepareMultiImageLegacySchema();
        file_put_contents(
            '/var/www/tests/_output/installer.installed',
            '4.5.2',
        );

        $I->amOnPage('/install.php?confirm3=1');
        $this->assertHealthyPage($I);
        $I->see('Successfully upgraded from 4.5.2');
        $I->assertSame(
            ['cycle', 'files', 'num_files', 'slug'],
            $this->postColumns(['cycle', 'files', 'num_files', 'slug']),
        );
        $I->assertSame([], $this->postColumns([
            'file',
            'fileheight',
            'filename',
            'filesize',
            'filewidth',
            'thumb',
            'thumbheight',
            'thumbwidth',
        ]));
    }

    public function upgradesAPreBanAppealDatabaseThroughEveryModernMigration(InstallerTester $I): void
    {
        $this->installFreshSite($I);
        $this->prepareMultiImageLegacySchema();
        $this->pdo->exec('DROP TABLE `ban_appeals`');
        file_put_contents(
            '/var/www/tests/_output/installer.installed',
            'v0.9.6-dev-21',
        );

        $I->amOnPage('/install.php?confirm2=1&confirm3=1');
        $this->assertHealthyPage($I);
        $I->see('Successfully upgraded from v0.9.6-dev-21');
        $I->assertSame('ban_appeals', $this->pdo->query("SHOW TABLES LIKE 'ban_appeals'")->fetchColumn());
    }

    public function migratesLegacyBanRangesBeforeTheModernSchemaUpgrade(InstallerTester $I): void
    {
        $this->installFreshSite($I);
        $this->prepareMultiImageLegacySchema();
        $this->pdo->exec('DROP TABLE `ban_appeals`, `bans`');
        $this->pdo->exec(
            'CREATE TABLE `bans` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `ip` varchar(255) NOT NULL,
                `set` int(10) unsigned NOT NULL,
                `expires` int(10) unsigned DEFAULT NULL,
                `board` varchar(58) DEFAULT NULL,
                `mod` int(10) NOT NULL,
                `reason` text,
                `seen` tinyint(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4',
        );
        $insert = $this->pdo->prepare(
            'INSERT INTO `bans` (`ip`, `set`, `expires`, `board`, `mod`, `reason`, `seen`)
             VALUES (?, ?, ?, ?, 1, ?, ?)',
        );
        $insert->execute(['198.51.100.0/28', time(), time() + 3600, 'b', 'legacy range', 1]);
        $insert->execute(['203.0.113.8', time(), null, null, null, 0]);
        $insert->execute(['not-an-ip', time(), null, null, 'invalid range', 0]);
        file_put_contents(
            '/var/www/tests/_output/installer.installed',
            'v0.9.6-dev-20',
        );

        $I->amOnPage('/install.php?confirm2=1&confirm3=1');
        $this->assertHealthyPage($I);
        $I->see('Successfully upgraded from v0.9.6-dev-20');
        $I->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM `bans`')->fetchColumn());
    }

    private function installFreshSite(InstallerTester $I): void
    {
        $siteDirectory = '/var/www/tests/_output/installer-site';
        if (!is_dir($siteDirectory)) {
            mkdir($siteDirectory, 0777, true);
        }

        $I->amOnPage('/install.php?step=4');
        $this->assertHealthyPage($I);
        $I->see('Installation complete');
        $I->dontSee('SQL errors');
    }

    private function prepareMultiImageLegacySchema(): void
    {
        $this->pdo->exec(
            'ALTER TABLE `posts_b`
                ADD `thumb` varchar(255) DEFAULT NULL,
                ADD `thumbwidth` int(11) DEFAULT NULL,
                ADD `thumbheight` int(11) DEFAULT NULL,
                ADD `file` varchar(255) DEFAULT NULL,
                ADD `fileheight` int(11) DEFAULT NULL,
                ADD `filesize` int(11) DEFAULT NULL,
                ADD `filewidth` int(11) DEFAULT NULL,
                ADD `filename` text,
                DROP COLUMN `files`,
                DROP COLUMN `num_files`,
                DROP COLUMN `slug`,
                DROP COLUMN `cycle`',
        );
        $this->pdo->exec('ALTER TABLE `mods` CHANGE `version` `salt` VARCHAR(64) NOT NULL');
        $this->pdo->exec('DROP TABLE `pages`, `captchas`, `search_queries`');
    }

    private function postColumns(array $columns): array
    {
        $quotedColumns = implode(', ', array_map([$this->pdo, 'quote'], $columns));

        return $this->pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'posts_b'
               AND COLUMN_NAME IN ({$quotedColumns})
             ORDER BY COLUMN_NAME",
        )->fetchAll(PDO::FETCH_COLUMN);
    }
}
