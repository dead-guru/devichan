<?php

declare(strict_types=1);

chdir(dirname(__DIR__, 2));

foreach ([
    'index.html',
    'index.html.gz',
    'recent.html',
    'recent.html.gz',
    'recent.json',
    'recent.json.gz',
    'sitemap.xml',
    'b/index.html',
    'b/index.html.gz',
    'b/res/1.html',
    'b/res/1.html.gz',
    'sec/index.html',
    'sec/index.html.gz',
    'sec/res/1.html',
    'sec/res/1.html.gz',
] as $generatedFile) {
    if (is_file($generatedFile)) {
        unlink($generatedFile);
    }
}

$databaseReady = false;
for ($attempt = 0; $attempt < 30; $attempt++) {
    try {
        new PDO(
            sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                getenv('MYSQL_HOST') ?: 'cmysql',
                getenv('MYSQL_DATABASE') ?: 'devichan_e2e',
            ),
            getenv('MYSQL_USER') ?: 'devichan_e2e',
            getenv('MYSQL_PASSWORD') ?: 'devichan_e2e',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $databaseReady = true;
        break;
    } catch (PDOException) {
        sleep(1);
    }
}

if (!$databaseReady) {
    throw new RuntimeException('E2E database did not become ready.');
}

require 'inc/bootstrap.php';

buildJavascript();

foreach (listBoards(true) as $boardName) {
    if (!openBoard($boardName)) {
        throw new RuntimeException("Unable to open /{$boardName}/");
    }

    $config['try_smarter'] = false;
    buildIndex();

    $threads = query(sprintf(
        'SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL ORDER BY `id`',
        $boardName,
    ));

    foreach ($threads->fetchAll(PDO::FETCH_COLUMN) as $threadId) {
        buildThread((int) $threadId);
    }
}

$themeFailures = [];
foreach (['index', 'catalog', 'recent', 'sitemap'] as $themeName) {
    try {
        rebuildTheme($themeName, 'all');
    } catch (Throwable $error) {
        $themeFailures[] = $themeName;
        fwrite(STDERR, sprintf(
            "Theme %s failed to build: %s\n",
            $themeName,
            $error->getMessage(),
        ));
    }
}

echo 'E2E application fixture is ready';
if ($themeFailures !== []) {
    echo ' with failed themes: ' . implode(', ', $themeFailures);
}
echo ".\n";
