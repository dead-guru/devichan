<?php
require 'inc/bootstrap.php';

if (!$config['stats']['enable']) {
    die(_("Stats is disabled"));
}

$stats = [
    'all_time' => [],
    '7d' => []
];

foreach (listBoards(true) as $boardsLink) { //TODO: count results of thread field grouping
    $queryThreads = prepare("SELECT COUNT(*) FROM ``posts_" . $boardsLink . "`` WHERE `thread` is NULL");
    $queryThreads->execute() or error(db_error($queryThreads));
    
    $queryReplays = prepare("SELECT COUNT(*) FROM ``posts_" . $boardsLink . "`` WHERE `thread` is not NULL");
    $queryReplays->execute() or error(db_error($queryReplays));
    
    $files = prepare("SELECT COUNT(num_files) FROM ``posts_" . $boardsLink . "`` WHERE `num_files` > 0");
    $files->execute() or error(db_error($files));
    
    $posters = prepare(
        "SELECT count(DISTINCT ip) FROM ``posts_" . $boardsLink . "`` GROUP BY ip"
    );
    $posters->execute() or error(db_error($posters));
    
    $stats['all_time'][$boardsLink] = [
        'board' => $boardsLink,
        'threads' => $queryThreads->fetchColumn(),
        'replays' => $queryReplays->fetchColumn(),
        'posters' => $posters->fetchColumn(),
        'files' => $files->fetchColumn(),
    ];
    
    $time = time();
    
    $last = DateTime::createFromFormat(
        'Y-m-d H:i:s',
        (new DateTime())->setTimestamp($time)->format('Y-m-d 23:59:59')
    )->getTimestamp();
    $first = DateTime::createFromFormat(
        'Y-m-d H:i:s',
        (new DateTime())->modify('-7d')->setTimestamp($time)->format('Y-m-d 00:00:00')
    )->getTimestamp();
    
    $queryThreads7d = prepare(
        "SELECT COUNT(*) FROM ``posts_" . $boardsLink . "`` WHERE `thread` is NULL AND time BETWEEN :first AND :last"
    );
    $queryThreads7d->bindValue(':first', $first, PDO::PARAM_INT);
    $queryThreads7d->bindValue(':last', $last, PDO::PARAM_INT);
    $queryThreads7d->execute() or error(db_error($queryThreads7d));
    
    $queryReplays7d = prepare(
        "SELECT COUNT(*) FROM ``posts_" . $boardsLink . "`` WHERE `thread` is not NULL AND time BETWEEN :first AND :last"
    );
    $queryReplays7d->bindValue(':first', $first, PDO::PARAM_INT);
    $queryReplays7d->bindValue(':last', $last, PDO::PARAM_INT);
    $queryReplays7d->execute() or error(db_error($queryReplays7d));
    
    $files7d = prepare(
        "SELECT COUNT(num_files) FROM ``posts_" . $boardsLink . "`` WHERE `num_files` > 0 AND time BETWEEN :first AND :last"
    );
    $files7d->bindValue(':first', $first, PDO::PARAM_INT);
    $files7d->bindValue(':last', $last, PDO::PARAM_INT);
    $files7d->execute() or error(db_error($files7d));
    
    $posters7d = prepare(
        "SELECT count(DISTINCT ip) FROM ``posts_" . $boardsLink . "``WHERE time BETWEEN :first AND :last GROUP BY ip"
    );
    $posters7d->bindValue(':first', $first, PDO::PARAM_INT);
    $posters7d->bindValue(':last', $last, PDO::PARAM_INT);
    $posters7d->execute() or error(db_error($posters7d));
    
    $stats['sd'][$boardsLink] = [
        'board' => $boardsLink,
        'threads' => $queryThreads7d->fetchColumn(),
        'replays' => $queryReplays7d->fetchColumn(),
        'posters' => $posters7d->fetchColumn(),
        'files' => $files7d->fetchColumn(),
    ];
}

$body = Element('stats.html', ['stats' => $stats]);

echo Element($config['file_page_template'], [
    'config' => $config,
    'title' => _('Statistics'),
    'subtitle' => _('Board Statistics'),
    'boardlist' => createBoardlist(),
    'body' => '' . $body
]);
