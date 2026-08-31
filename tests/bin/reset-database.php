<?php

declare(strict_types=1);

chdir(dirname(__DIR__, 2));

$database = getenv('MYSQL_DATABASE') ?: 'devichan_e2e';
$pdo = new PDO(
    sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        getenv('MYSQL_HOST') ?: 'cmysql',
        $database,
    ),
    getenv('MYSQL_USER') ?: 'devichan_e2e',
    getenv('MYSQL_PASSWORD') ?: 'devichan_e2e',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ],
);

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $pdo->exec(sprintf(
        'DROP TABLE `%s`',
        str_replace('`', '``', (string) $table),
    ));
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$schema = file_get_contents('install.sql');
$seed = file_get_contents('tests/fixtures/seed.sql');
if ($schema === false || $seed === false) {
    throw new RuntimeException('The database fixture files are not readable.');
}

$seed = preg_replace(
    '/^\s*USE\s+`?' . preg_quote($database, '/') . '`?\s*;\s*/i',
    '',
    $seed,
);
if ($seed === null) {
    throw new RuntimeException('The database fixture is invalid.');
}

$pdo->exec($schema);
$pdo->exec($seed);

echo "E2E database fixture was reset.\n";
