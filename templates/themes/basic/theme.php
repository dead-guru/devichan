<?php
require 'info.php';
require_once __DIR__ . '/activity.php';

function basic_build($action, $settings, $board) {
    Basic::build($action, $settings);
}

class Basic {
    public static function build($action, $settings) {
        global $config;

        if (empty($settings['active_homepage'])) {
            if ($action == 'all' || $action == 'news') {
                file_write($config['dir']['home'] . $settings['file'], self::homepage($settings));
            }
            return true;
        }
        if (!in_array($action, ['all', 'news', 'boards', 'post', 'post-thread', 'post-delete', 'homepage'], true)) {
            return true;
        }

        $lock = null;
        try {
            $lock = fopen('tmp/locks/basic-homepage.lock', 'c');
            if (!$lock) {
                throw new RuntimeException('Could not open homepage lock.');
            }
            $nonblock = $action === 'post' || $action === 'post-thread';
            if (!flock($lock, LOCK_EX | ($nonblock ? LOCK_NB : 0))) {
                return true;
            }
            $refresh = in_array($action, ['all', 'boards', 'post-delete', 'homepage'], true);
            self::publish($config['dir']['home'] . $settings['file'], self::homepage($settings, $refresh));
            return true;
        } catch (Throwable $e) {
            error_log('Homepage rebuild failed: ' . $e->getMessage());
            return false;
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    public static function homepage($settings, bool $refresh = false) {
        global $config;

        $settings['no_recent'] = (int)$settings['no_recent'];
        $logos = preg_split('/\s*[\r\n]+\s*/', trim($settings['logo'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $query = query('SELECT * FROM ``news`` ORDER BY `time` DESC' . ($settings['no_recent'] ? ' LIMIT ' . $settings['no_recent'] : ''));
        if (!$query) {
            throw new RuntimeException('Could not read homepage news.');
        }
        $data = [
            'settings' => $settings, 'logos' => $logos, 'config' => $config,
            'boardlist' => createBoardlist(), 'news' => $query->fetchAll(PDO::FETCH_ASSOC),
        ];
        if (empty($settings['active_homepage'])) {
            return Element('themes/basic/index.html', $data);
        }
        $data['activity'] = BasicActivity::snapshot($settings, $refresh);
        $data['navigation'] = self::navigation($settings, $data['activity']);
        foreach (['css', 'js'] as $extension) {
            $file = 'active.' . $extension;
            $data['assets'][$extension] = $config['root'] . trim($config['dir']['themes_uri'], '/') . '/basic/' . $file
                . '?v=' . substr(hash_file('sha256', __DIR__ . '/' . $file), 0, 12);
        }
        return Element('themes/basic/active.html', $data);
    }

    private static function navigation(array $settings, array $activity): array {
        global $config;

        $links = [];
        if (trim($settings['board_links'] ?? '') !== '') {
            foreach (preg_split('/\r?\n/', trim($settings['board_links'])) as $line) {
                $parts = preg_split('/\s+/', trim($line), 2);
                if (count($parts) === 2) {
                    $links[trim($parts[0], '/')] = $parts[1];
                }
            }
        } else {
            foreach ($config['index_boards'] as $column) {
                foreach ($column as $category) {
                    foreach ($category as $uri => $title) {
                        $links[$uri] = $title;
                    }
                }
            }
        }
        $known = array_column(listBoards(), 'title', 'uri');
        $counts = array_column($activity['boards'], 'posts_7d', 'uri');
        $navigation = [];
        foreach ($links as $uri => $title) {
            if (!isset($known[$uri]) || array_key_exists($uri, $config['secret_boards'] ?? [])) {
                continue;
            }
            $title = trim(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $adult = preg_match('/\s*\(?18\+\)?\s*$/u', $title) === 1;
            if ($adult) {
                $title = trim(preg_replace('/\s*\(?18\+\)?\s*$/u', '', $title));
            }
            $navigation[] = [
                'uri' => (string)$uri, 'title' => $title, 'adult' => $adult, 'posts_7d' => $counts[$uri] ?? null,
                'link' => $config['root'] . sprintf($config['board_path'], $uri),
            ];
        }
        return $navigation;
    }

    private static function publish(string $path, string $html): void {
        global $config;

        if (!is_dir(dirname($path)) || !is_writable(dirname($path))) {
            throw new RuntimeException('Homepage directory is not writable.');
        }
        $tmp = tempnam(dirname($path), '.homepage-');
        if ($tmp === false) {
            throw new RuntimeException('Could not stage homepage.');
        }
        try {
            if (file_put_contents($tmp, $html) !== strlen($html)) {
                throw new RuntimeException('Could not write homepage.');
            }
            self::permissions($tmp, $path);
            if ($config['gzip_static']) {
                $gzip = gzencode($html);
                if ($gzip === false || file_put_contents($tmp . '.gz', $gzip) !== strlen($gzip)) {
                    throw new RuntimeException('Could not compress homepage.');
                }
                self::permissions($tmp . '.gz', is_file($path . '.gz') ? $path . '.gz' : $path);
                if (!rename($tmp . '.gz', $path . '.gz')) {
                    throw new RuntimeException('Could not publish compressed homepage.');
                }
            }
            if (!rename($tmp, $path)) {
                throw new RuntimeException('Could not publish homepage.');
            }
            event('write', $path);
        } finally {
            if (is_file($tmp)) unlink($tmp);
            if (is_file($tmp . '.gz')) unlink($tmp . '.gz');
        }
    }

    private static function permissions(string $tmp, string $path): void {
        $stat = is_file($path) ? stat($path) : false;
        if ($stat) {
            if (fileowner($tmp) !== $stat['uid'] && !chown($tmp, $stat['uid'])) {
                throw new RuntimeException('Could not preserve homepage owner.');
            }
            if (filegroup($tmp) !== $stat['gid'] && !chgrp($tmp, $stat['gid'])) {
                throw new RuntimeException('Could not preserve homepage group.');
            }
        }
        if (!chmod($tmp, $stat ? $stat['mode'] & 0777 : 0644)) {
            throw new RuntimeException('Could not set homepage permissions.');
        }
    }
}
