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

function writeFixtureImage(string $path, int $width, int $height, string $format): void
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 64, 128, 192));
    $written = $format === 'jpg'
        ? imagejpeg($image, $path, 90)
        : imagepng($image, $path);
    imagedestroy($image);

    if (!$written) {
        throw new RuntimeException("Unable to create fixture image {$path}.");
    }
}

buildJavascript();

foreach (listBoards(true) as $boardName) {
    if (!openBoard($boardName)) {
        throw new RuntimeException("Unable to open /{$boardName}/");
    }

    if ($boardName === 'b') {
        writeFixtureImage($board['dir'] . $config['dir']['img'] . '1700000000001.jpg', 640, 480, 'jpg');
        writeFixtureImage($board['dir'] . $config['dir']['thumb'] . '1700000000001.png', 160, 120, 'png');
        writeFixtureImage($board['dir'] . $config['dir']['img'] . '1700000000002.png', 480, 640, 'png');
        writeFixtureImage($board['dir'] . $config['dir']['thumb'] . '1700000000002.png', 90, 120, 'png');
        if (file_put_contents(
            $board['dir'] . $config['dir']['img'] . '1700000000003.txt',
            "Thread gallery fixture\n",
        ) === false) {
            throw new RuntimeException('Unable to create the text-file fixture.');
        }
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
