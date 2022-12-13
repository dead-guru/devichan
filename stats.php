<?php
require 'inc/bootstrap.php';

if (!$config['stats']['enable']) {
    die(_("Stats is disabled"));
}

$stats = [
    'all_time' => [],
    '7d' => []
];


dump($_SERVER);


foreach (listBoards(true) as $boardsLink) { //TODO: count results of thread field grouping
    $queryThreads = prepare("SELECT COUNT(*) FROM ``posts_" . $boardsLink . "`` WHERE `thread` is NULL");
    $queryThreads->execute() or error(db_error($queryThreads));
    
    $queryReplays = prepare("SELECT COUNT(*) FROM ``posts_" . $boardsLink . "`` WHERE `thread` is not NULL");
    $queryReplays->execute() or error(db_error($queryReplays));
    
    $time = time();
    
    $last = DateTime::createFromFormat('Y-m-d H:i:s', (new DateTime())->setTimestamp($time)->format('Y-m-d 23:59:59'))->getTimestamp();
    $first = DateTime::createFromFormat('Y-m-d H:i:s', (new DateTime())->modify('-7d')->setTimestamp($time)->format('Y-m-d 00:00:00'))->getTimestamp();
    
    $queryThreads7d = prepare("SELECT COUNT(*) FROM ``posts_" . $boardsLink . "`` WHERE `thread` is NULL AND time BETWEEN :first AND :last");
    $queryThreads7d->bindValue(':first', $first, PDO::PARAM_INT);
    $queryThreads7d->bindValue(':last', $last, PDO::PARAM_INT);
    $queryThreads7d->execute() or error(db_error($queryThreads7d));
    
    $queryReplays7d = prepare("SELECT COUNT(*) FROM ``posts_" . $boardsLink . "`` WHERE `thread` is not NULL AND time BETWEEN :first AND :last");
    $queryReplays7d->bindValue(':first', $first, PDO::PARAM_INT);
    $queryReplays7d->bindValue(':last', $last, PDO::PARAM_INT);
    $queryReplays7d->execute() or error(db_error($queryReplays7d));
    
    $stats['all_time'][$boardsLink] = [
        'board' => $boardsLink,
        'threads' => $queryThreads->fetchColumn(),
        'replays' => $queryReplays->fetchColumn()
    ];
    
    $stats['7d'][$boardsLink] = [
        'board' => $boardsLink,
        'threads' => $queryThreads7d->fetchColumn(),
        'replays' => $queryReplays7d->fetchColumn()
    ];
}
$body = Element('stats.html', ['stats' => $stats]);

echo Element($config['file_page_template'], [
    'config' => $config,
    'title' => _('Statistics'),
    'boardlist' => createBoardlist(),
    'body' => '' . $body
]);
