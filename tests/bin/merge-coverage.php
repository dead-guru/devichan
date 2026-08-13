<?php

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Clover;
use SebastianBergmann\CodeCoverage\Report\Html\Facade as HtmlReport;
use SebastianBergmann\CodeCoverage\Report\Text;
use SebastianBergmann\CodeCoverage\Report\Thresholds;

$testsDirectory = dirname(__DIR__);
$projectDirectory = dirname($testsDirectory);

require $projectDirectory . '/vendor/autoload.php';

$outputDirectory = $testsDirectory . '/_output';
$chunkFiles = array_slice($argv, 1);

if ($chunkFiles === []) {
    fwrite(STDERR, "Pass at least one coverage chunk.\n");
    exit(1);
}

$coverage = null;

foreach ($chunkFiles as $chunkFile) {
    if (!str_starts_with($chunkFile, '/')) {
        $chunkFile = $projectDirectory . '/' . $chunkFile;
    }

    if (!is_file($chunkFile)) {
        fwrite(STDERR, sprintf("Coverage chunk not found: %s\n", $chunkFile));
        exit(1);
    }

    $contents = file_get_contents($chunkFile);
    $chunk = str_starts_with(ltrim($contents), '<?php')
        ? require $chunkFile
        : unserialize($contents);

    if (!$chunk instanceof CodeCoverage) {
        fwrite(STDERR, sprintf("Invalid coverage chunk: %s\n", $chunkFile));
        exit(1);
    }

    if ($coverage === null) {
        $coverage = $chunk;
        continue;
    }

    $data = $coverage->getData();
    $data->merge($chunk->getData());
    $coverage->setData($data);
    $coverage->setTests(array_merge($coverage->getTests(), $chunk->getTests()));
}

$coverage->clearCache();

$report = $coverage->getReport();
$executableLines = $report->numberOfExecutableLines();
$executedLines = $report->numberOfExecutedLines();

if ($executableLines === 0 || $executedLines === 0) {
    fwrite(STDERR, "Merged coverage is empty; keeping the existing reports.\n");
    exit(1);
}

$htmlDirectory = $outputDirectory . '/coverage';
$cloverFile = $outputDirectory . '/coverage.xml';
$textFile = $outputDirectory . '/coverage.txt';
$reportSuffix = '.next-' . bin2hex(random_bytes(6));
$nextHtmlDirectory = $htmlDirectory . $reportSuffix;
$nextCloverFile = $cloverFile . $reportSuffix;
$nextTextFile = $textFile . $reportSuffix;

(new HtmlReport('Codeception E2E'))->process($coverage, $nextHtmlDirectory);
(new Clover())->process($coverage, $nextCloverFile, 'Devichan E2E');

$text = (new Text(Thresholds::default(), true))->process($coverage);
file_put_contents($nextTextFile, $text);

$previousHtmlDirectory = $htmlDirectory . '.previous-' . bin2hex(random_bytes(6));
if (is_dir($htmlDirectory) && !rename($htmlDirectory, $previousHtmlDirectory)) {
    fwrite(STDERR, "Unable to preserve the existing HTML coverage report.\n");
    exit(1);
}

if (!rename($nextHtmlDirectory, $htmlDirectory)) {
    if (is_dir($previousHtmlDirectory)) {
        rename($previousHtmlDirectory, $htmlDirectory);
    }
    fwrite(STDERR, "Unable to publish the new HTML coverage report.\n");
    exit(1);
}

rename($nextCloverFile, $cloverFile);
rename($nextTextFile, $textFile);

if (is_dir($previousHtmlDirectory)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $previousHtmlDirectory,
            FilesystemIterator::SKIP_DOTS,
        ),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($previousHtmlDirectory);
}

echo $text;

$minimumCoverage = filter_var(
    getenv('COVERAGE_MIN') ?: '85',
    FILTER_VALIDATE_FLOAT,
);
if ($minimumCoverage === false || $minimumCoverage < 0 || $minimumCoverage > 100) {
    fwrite(STDERR, "COVERAGE_MIN must be a number from 0 through 100.\n");
    exit(1);
}

$coveragePercentage = ($executedLines / $executableLines) * 100;
if ($coveragePercentage < $minimumCoverage) {
    fwrite(
        STDERR,
        sprintf(
            "Line coverage %.2f%% is below the required %.2f%%.\n",
            $coveragePercentage,
            $minimumCoverage,
        ),
    );
    exit(1);
}
