<?php

declare(strict_types=1);

$config['db']['type'] = 'mysql';
$config['db']['server'] = getenv('MYSQL_HOST') ?: 'cmysql';
$config['db']['user'] = getenv('MYSQL_USER') ?: 'devichan_e2e';
$config['db']['password'] = getenv('MYSQL_PASSWORD') ?: 'devichan_e2e';
$config['db']['database'] = getenv('MYSQL_DATABASE') ?: 'devichan_e2e';
$config['locale'] = 'en';

$config['cookies']['salt'] = 'devichan-e2e-only-cookie-salt';
$config['cache']['enabled'] = false;
if (($_COOKIE['e2e_cache'] ?? null) === 'php') {
    $config['cache']['enabled'] = 'php';
}
$e2eThumbMethod = $_COOKIE['e2e_thumb_method'] ?? null;
if (in_array($e2eThumbMethod, ['gd', 'imagick'], true)) {
    $config['thumb_method'] = $e2eThumbMethod;
}
if (($_COOKIE['e2e_allow_webp'] ?? null) === '1') {
    $config['allowed_ext'][] = 'webp';
}
if (($_COOKIE['e2e_upload_by_url'] ?? null) === '1') {
    $config['allow_upload_by_url'] = true;
}
if (($_COOKIE['e2e_cycle_limit'] ?? null) === '2') {
    $config['cycle_limit'] = 2;
}
if (($_COOKIE['e2e_reply_hard_limit'] ?? null) === '1') {
    $config['reply_hard_limit'] = 1;
}
$e2ePostCase = $_SERVER['HTTP_X_E2E_POST_CASE'] ?? $_COOKIE['e2e_post_case'] ?? null;
switch ($e2ePostCase) {
    case 'locked':
        $config['board_locked'] = true;
        break;
    case 'no-delete':
        $config['allow_delete'] = false;
        break;
    case 'report-limit':
        $config['report_limit'] = 1;
        break;
    case 'report-captcha':
        $config['report_captcha'] = true;
        break;
    case 'disabled-fields':
        $config['field_disable_name'] = true;
        $config['field_disable_email'] = true;
        $config['field_disable_password'] = true;
        $config['field_disable_subject'] = true;
        $config['field_disable_reply_subject'] = true;
        break;
    case 'force-image':
        $config['force_image_op'] = true;
        break;
    case 'force-body':
        $config['force_body_op'] = true;
        $config['force_body'] = true;
        break;
    case 'invalid-multiimage':
        $config['multiimage_method'] = 'invalid-e2e-method';
        break;
    case 'each-multiimage':
        $config['multiimage_method'] = 'each';
        break;
    case 'tiny-file-limit':
        $config['max_filesize'] = 1;
        break;
    case 'zero-image-limit':
        $config['max_images'] = 0;
        break;
    case 'restricted-op-extension':
        $config['allowed_ext_op'] = ['jpeg'];
        break;
    case 'strip-combining':
        $config['strip_combining_chars'] = true;
        break;
    case 'noko-email':
        $config['always_noko'] = false;
        break;
}
$config['debug'] = false;
$config['syslog'] = false;
$config['referer_match'] = false;
$config['minify_css'] = false;
$config['purge'] = [];

$config['captcha']['enabled'] = false;
$config['new_thread_capt'] = false;
$config['recaptcha'] = false;
$config['report_captcha'] = false;
$config['delete_time'] = 0;
$config['flood_time'] = 1;
$config['flood_time_ip'] = 1;
$config['flood_time_same'] = 1;
if ($e2ePostCase === 'rate-limit') {
    $config['flood_time'] = 60;
    $config['flood_time_ip'] = 60;
    $config['flood_time_same'] = 60;
}

$config['search']['enable'] = true;
$config['search']['queries_per_minutes'] = [1000, 1];
$config['search']['queries_per_minutes_all'] = [1000, 1];
$config['stats']['enable'] = true;
$config['public_logs'] = 1;
$config['api']['enabled'] = true;
$config['api']['auth_keys'] = ['e2e-api-key'];
$config['enable_embedding'] = true;
$config['always_noko'] = true;
if ($e2ePostCase === 'report-captcha') {
    $config['report_captcha'] = true;
}
if ($e2ePostCase === 'noko-email') {
    $config['always_noko'] = false;
}
if (($_SERVER['HTTP_X_E2E_CONFIG_EDITOR'] ?? null) === 'php') {
    $config['mod']['config_editor_php'] = true;
}

if (isset($_GET['e2e_move']) || isset($_POST['e2e_move'])) {
    $config['mod']['move'] = MOD;
    $config['move_replies'] = true;
}

$config['user_flag'] = true;
$config['user_flags'] = ['ua' => 'Ukraine'];
if (isset($_POST['e2e_tags'])) {
    $config['allowed_tags'] = ['e2e' => 'E2E'];
}
if (isset($_POST['e2e_ban_appeals'])) {
    $config['ban_appeals'] = true;
}
$config['wordfilters'][] = ['E2E replace me', 'E2E replaced'];
$config['filters'][] = [
    'condition' => ['body' => '/E2E blocked by filter/'],
    'action' => 'reject',
    'message' => 'E2E filter rejection',
    'add_note' => true,
];

switch ($_POST['e2e_filter_case'] ?? $_COOKIE['e2e_filter_case'] ?? null) {
    case 'fields':
        $config['filters'][] = [
            'condition' => [
                'name' => '/^E2E Filter Name$/',
                'email' => '/^filter@example\.com$/',
                'subject' => '/^E2E Filter Subject$/',
                'body' => '/^E2E compound field filter [a-f0-9]+$/',
                'password' => 'e2e-filter-fields',
                'op' => true,
                'has_file' => false,
                'board' => 'b',
                '!trip' => 'not-this-trip',
                'custom' => static fn(array $post): bool => $post['op'] === true,
            ],
            'action' => 'reject',
            'message' => 'E2E compound filter rejection',
        ];
        break;

    case 'file':
        $config['filters'][] = [
            'condition' => [
                'body' => '/^E2E file filter$/',
                'has_file' => true,
                'filename' => '/^e2e-filter-file-[a-f0-9]+\.png$/',
                'extension' => '/^png$/',
            ],
            'action' => 'reject',
            'message' => 'E2E file filter rejection',
        ];
        break;

    case 'ban':
        $filterToken = preg_replace('/[^a-f0-9]/', '', $_POST['e2e_filter_token'] ?? '');
        $config['filters'][] = [
            'condition' => [
                'body' => '/^E2E filter autoban ' . preg_quote($filterToken, '/') . '$/',
            ],
            'action' => 'ban',
            'reason' => 'E2E automatic filter ban ' . $filterToken,
            'expires' => '1 hour',
            'all_boards' => true,
        ];
        break;

    case 'flood':
        $config['flood_time'] = -1;
        $config['flood_time_ip'] = -1;
        $config['flood_time_same'] = -1;
        $config['flood_cache'] = 120;
        $config['filters'][] = [
            'condition' => [
                'flood-match' => ['ip', 'board', 'isreply'],
                'flood-time' => 120,
                'flood-count' => 1,
            ],
            'action' => 'reject',
            'message' => 'E2E flood filter rejection',
        ];
        break;
}

$config['secret_boards'] = [
    'sec' => [
        '$2y$10$XDITd4QTbiWckw3TmzKec.a3PGNrRisceB1sspc/UBU4U2ZDCIsTi',
    ],
];
$config['secret_boards_ttl'] = 3600;

if (($_COOKIE['e2e_load_config'] ?? null) === 'defaults') {
    foreach ([
        'global_message',
        'post_url',
        'referer_match',
        'image_blank',
        'image_sticky',
        'image_locked',
        'image_bumplocked',
        'image_deleted',
        'uri_thumb',
        'uri_img',
        'uri_stylesheets',
        'url_stylesheet',
        'url_javascript',
        'additional_javascript_url',
        'uri_flags',
        'user_flag',
        'user_flags',
    ] as $key) {
        unset($config[$key]);
    }
    unset($config['cookies']['path'], $config['dir']['static']);
    $config['allow_roll'] = true;
    $config['allowed_ext_files'][] = 'webm';
    $config['anonymous'] = ['E2E Anonymous'];
    $config['debug'] = true;
    $config['verbose_errors'] = true;
    $config['deprecation_errors'] = false;
    $config['cache']['enabled'] = 'php';
}
