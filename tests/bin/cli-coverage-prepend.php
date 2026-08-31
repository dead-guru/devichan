<?php

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\XdebugDriver;
use SebastianBergmann\CodeCoverage\Filter;

$coveragePrefix = getenv('E2E_CLI_COVERAGE_PREFIX');
if ($coveragePrefix === false || $coveragePrefix === '') {
    return;
}

$projectDirectory = dirname(__DIR__, 2);

require $projectDirectory . '/vendor/autoload.php';

$filter = new Filter();
foreach (['inc', 'auth', 'templates/themes', 'tools'] as $directory) {
    $filter->includeDirectory($projectDirectory . '/' . $directory);
}
foreach ([
    'banned.php',
    'boards.php',
    'install.php',
    'log.php',
    'mod.php',
    'post.php',
    'report.php',
    'search.php',
    'smart_build.php',
    'stats.php',
] as $file) {
    $filter->includeFile($projectDirectory . '/' . $file);
}

$filter->excludeDirectory($projectDirectory . '/inc/lib');
$filter->excludeFile($projectDirectory . '/inc/image/bmp.php');
$filter->excludeFile($projectDirectory . '/inc/secrets.php');

$coverage = new CodeCoverage(new XdebugDriver($filter), $filter);
$coverage->start('cli-entrypoint-' . getmypid());

register_shutdown_function(static function () use ($coverage, $coveragePrefix): void {
    try {
        $coverage->stop();
        $target = $coveragePrefix . '-' . getmypid() . '.cov';
        $serialized = base64_encode(serialize($coverage));
        file_put_contents(
            $target,
            "<?php\nreturn unserialize(base64_decode('{$serialized}'));\n",
        );
    } catch (Throwable $error) {
        file_put_contents(
            $coveragePrefix . '-' . getmypid() . '.error.log',
            $error::class . ': ' . $error->getMessage() . "\n",
        );
    }
});
