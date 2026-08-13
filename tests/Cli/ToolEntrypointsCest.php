<?php

declare(strict_types=1);

namespace DevichanE2E\Cli;

use DevichanE2E\Support\CliTester;
use Symfony\Component\Process\Process;

final class ToolEntrypointsCest
{
    private const PROJECT_DIRECTORY = '/var/www';
    private const OUTPUT_DIRECTORY = '/var/www/tests/_output';
    private const PREPEND = '/var/www/tests/bin/cli-coverage-prepend.php';

    public function fullRebuildExercisesPostAndThreadRegeneration(CliTester $I): void
    {
        $full = $this->run('rebuild-full', [
            'php', '-d', 'auto_prepend_file=' . self::PREPEND,
            'tools/rebuild.php', '--full', '--board', 'b',
        ]);
        $I->assertSame(0, $full->getExitCode(), $full->getErrorOutput());
        $I->assertStringContainsString('Complete!', $full->getOutput());
    }

    public function quickRebuildExercisesStaticGeneration(CliTester $I): void
    {
        $quick = $this->run('rebuild-quick', [
            'php', '-d', 'auto_prepend_file=' . self::PREPEND,
            'tools/rebuild.php', '--quick', '--quiet', '--board', 'b',
        ]);
        $I->assertSame(0, $quick->getExitCode(), $quick->getErrorOutput());
    }

    public function parallelRebuildExercisesEveryRequestedMode(CliTester $I): void
    {
        $parallel = $this->run('rebuild2', [
            'php', '-d', 'auto_prepend_file=' . self::PREPEND,
            'tools/rebuild2.php',
            '--board', 'b',
            '--themes',
            '--js',
            '--indexes',
            '--threads',
            '--postmarkup',
            '--api',
            '--cache',
            '--processes', '1',
        ], 180);
        $I->assertSame(0, $parallel->getExitCode(), $parallel->getErrorOutput());
        $I->assertStringContainsString('Complete!', $parallel->getOutput());
    }

    public function benchmarkEntrypointProcessesARealImage(CliTester $I): void
    {
        $source = self::OUTPUT_DIRECTORY . '/cli-benchmark.png';
        $image = imagecreatetruecolor(24, 24);
        imagefill($image, 0, 0, imagecolorallocate($image, 80, 120, 160));
        $I->assertTrue(imagepng($image, $source));
        imagedestroy($image);

        $benchmark = $this->run('benchmark', [
            'php', '-d', 'auto_prepend_file=' . self::PREPEND,
            'tools/benchmark.php', $source, '1',
        ], 180);
        $I->assertSame(0, $benchmark->getExitCode(), $benchmark->getErrorOutput());
        $I->assertStringContainsString('Method: gd', $benchmark->getOutput());
    }

    public function strayImageCleanerChecksEveryBoard(CliTester $I): void
    {
        $stray = $this->run('delete-stray-images', [
            'php', '-d', 'auto_prepend_file=' . self::PREPEND,
            'tools/delete-stray-images.php',
        ]);
        $I->assertSame(0, $stray->getExitCode(), $stray->getErrorOutput());
        $I->assertStringContainsString('/b/', $stray->getOutput());
    }

    public function localizationEntrypointsParseAndCompileFixtures(CliTester $I): void
    {
        $directory = self::OUTPUT_DIRECTORY . '/cli-i18n';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $javascript = $directory . '/messages.js';
        $catalog = $directory . '/messages.po';
        $json = $directory . '/messages.json.js';
        file_put_contents($javascript, "const message = _('CLI coverage');\n");
        file_put_contents($catalog, <<<'PO'
msgid ""
msgstr ""
"Language: uk\\n"

msgid "CLI coverage"
msgstr "CLI coverage translated"
PO);

        $extract = $this->run('jsgettext', [
            'php', '-d', 'auto_prepend_file=' . self::PREPEND,
            'jsgettext.php', '-o', $catalog, $javascript,
        ], 60, self::PROJECT_DIRECTORY . '/tools/inc/lib/jsgettext');
        $I->assertSame(0, $extract->getExitCode(), $extract->getErrorOutput());
        $I->assertStringContainsString('CLI coverage', (string) file_get_contents($catalog));

        $compile = $this->run('po2json', [
            'php', '-d', 'auto_prepend_file=' . self::PREPEND,
            'po2json.php', '-i', $catalog, '-o', $json, '-n', 'e2eTranslations',
        ], 60, self::PROJECT_DIRECTORY . '/tools/inc/lib/jsgettext');
        $I->assertSame(0, $compile->getExitCode(), $compile->getErrorOutput());
        $I->assertStringContainsString('e2eTranslations = ', (string) file_get_contents($json));

        $missingLocale = $this->run('i18n-compile-missing', [
            'php', '-d', 'auto_prepend_file=' . self::PREPEND,
            'tools/i18n_compile.php', '--locale', 'e2e_DOES_NOT_EXIST',
        ]);
        $I->assertStringContainsString('does not exist', $missingLocale->getOutput());
    }

    public function projectLocalizationToolsExtractAndCompileANewLocale(CliTester $I): void
    {
        $locale = 'e2e_CLI';
        $localeDirectory = self::PROJECT_DIRECTORY . '/inc/locale/' . $locale;

        $extract = $this->run('i18n-extract', [
            'php', '-d', 'auto_prepend_file=' . self::PREPEND,
            'tools/i18n_extract.php', '--locale', $locale,
        ], 180);
        $I->assertSame(0, $extract->getExitCode(), $extract->getErrorOutput());
        $I->assertFileExists($localeDirectory . '/LC_MESSAGES/tinyboard.po');
        $I->assertFileExists($localeDirectory . '/LC_MESSAGES/javascript.po');

        $compile = $this->run('i18n-compile', [
            'php', '-d', 'auto_prepend_file=' . self::PREPEND,
            'tools/i18n_compile.php', '--locale', $locale,
        ]);
        $I->assertSame(0, $compile->getExitCode(), $compile->getErrorOutput());
        $I->assertFileExists($localeDirectory . '/LC_MESSAGES/tinyboard.mo');
        $I->assertFileExists($localeDirectory . '/LC_MESSAGES/javascript.js');
    }

    public function legacyRulesImporterPersistsBoardRules(CliTester $I): void
    {
        $rules = self::PROJECT_DIRECTORY . '/b/rules.txt';
        file_put_contents($rules, '<p>Integration board rules</p>');

        $import = $this->run('import-rules', [
            'php', '-d', 'auto_prepend_file=' . self::PREPEND,
            'tools/import_rules.php',
        ]);
        $I->assertSame(0, $import->getExitCode(), $import->getErrorOutput());

        $pdo = new \PDO(
            (string) getenv('E2E_DB_DSN'),
            (string) getenv('E2E_DB_USER'),
            (string) getenv('E2E_DB_PASSWORD'),
        );
        $statement = $pdo->query(
            "SELECT `content` FROM `pages` WHERE `board` = 'b' AND `name` = 'rules' ORDER BY `id` DESC LIMIT 1",
        );
        $I->assertSame('<p>Integration board rules</p>', $statement->fetchColumn());

        $pdo->exec("DELETE FROM `pages` WHERE `board` = 'b' AND `name` = 'rules'");
        unlink($rules);
    }

    public function moderatorLoginFormRendersItsConfiguredTemplate(CliTester $I): void
    {
        $login = $this->run('runtime-login', [
            'php', '-d', 'auto_prepend_file=' . self::PREPEND,
            'tests/fixtures/cli-runtime-entrypoint.php', 'login',
        ]);
        $I->assertSame(0, $login->getExitCode(), $login->getErrorOutput());
        $I->assertStringContainsString('Login', $login->getOutput());
        $I->assertStringContainsString('integration-user', $login->getOutput());
    }

    public function bootstrapErrorEntrypointRendersWithoutTheMainTemplate(CliTester $I): void
    {
        $basicError = $this->run('runtime-basic-error', [
            'php', '-d', 'auto_prepend_file=' . self::PREPEND,
            'tests/fixtures/cli-runtime-entrypoint.php', 'basic-error',
        ]);
        $I->assertSame(0, $basicError->getExitCode(), $basicError->getErrorOutput());
        $I->assertStringContainsString('Integration bootstrap error', $basicError->getOutput());
    }

    public function commandLineErrorEntrypointPrintsItsFailure(CliTester $I): void
    {
        $cliError = $this->run('runtime-cli-error', [
            'php', '-d', 'auto_prepend_file=' . self::PREPEND,
            'tests/fixtures/cli-runtime-entrypoint.php', 'cli-error',
        ]);
        $I->assertSame(0, $cliError->getExitCode(), $cliError->getErrorOutput());
        $I->assertStringContainsString('Error: Integration CLI error', $cliError->getOutput());
    }

    private function run(
        string $coverageName,
        array $command,
        int $timeout = 120,
        string $workingDirectory = self::PROJECT_DIRECTORY,
    ): Process {
        $prefix = self::OUTPUT_DIRECTORY . '/coverage-chunks/cli-' . $coverageName;
        foreach (glob($prefix . '-*') ?: [] as $oldChunk) {
            unlink($oldChunk);
        }

        $process = new Process($command, $workingDirectory, [
            'E2E_CLI_COVERAGE_PREFIX' => $prefix,
            'TINYBOARD_PATH' => self::PROJECT_DIRECTORY,
            'XDEBUG_MODE' => 'coverage',
        ]);
        $process->setTimeout($timeout);
        $process->run();

        return $process;
    }
}
