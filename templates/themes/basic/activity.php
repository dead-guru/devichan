<?php

class BasicActivity {
    public static function snapshot(array $settings, bool $refresh = false): array {
        global $config;

        $allowed = preg_split('/\s+/', trim($settings['activity_boards'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $private = array_keys($config['secret_boards'] ?? []);
        $boards = array_values(array_filter(self::rows('SELECT `uri`, `title` FROM ``boards`` ORDER BY `uri`'),
            fn($board) => in_array($board['uri'], $allowed, true) && !in_array($board['uri'], $private, true)));
        $key = 'basic_activity_' . hash('sha256', json_encode([$boards, $config['root'], $config['board_path'],
            $config['dir']['res'], $config['slugify'], $config['file_page'], $config['file_page_slug']]));
        $now = time();
        $cached = !empty($config['cache']['enabled']) ? Cache::get($key) : false;
        if (!$refresh && is_array($cached) && isset($cached['generated_at'], $cached['data'])
            && is_int($cached['generated_at']) && is_array($cached['data'])
            && $cached['generated_at'] <= $now && $cached['generated_at'] > $now - 300
            && isset($cached['data']['boards'], $cached['data']['threads'], $cached['data']['replies'], $cached['data']['stats'], $cached['data']['hours'])) {
            return $cached['data'];
        }

        $data = self::collect($boards, $now);
        if (!empty($config['cache']['enabled'])) {
            Cache::set($key, ['generated_at' => $now, 'data' => $data], 300);
        }
        return $data;
    }

    private static function collect(array $boards, int $now): array {
        global $config, $board, $build_pages, $current_locale;

        $saved = [$config, $board ?? null, $build_pages ?? null, $current_locale ?? null];
        $day = $now - 86400;
        $week = $now - 604800;
        $data = ['generated_at' => $now, 'boards' => [], 'threads' => [], 'replies' => [], 'stats' => [
            'posts_24h' => 0, 'replies_24h' => 0, 'threads_24h' => 0, 'threads_7d' => 0,
            'total_threads' => 0, 'media_bytes' => 0, 'peak_hour' => 0,
        ], 'hours' => []];
        for ($i = 0; $i < 24; $i++) {
            $data['hours'][] = ['time' => $day + $i * 3600, 'count' => 0];
        }

        try {
            foreach ($boards as $info) {
                if (!openBoard($info['uri'])) {
                    throw new RuntimeException('Homepage board no longer exists.');
                }
                $uri = $info['uri'];
                $table = '``posts_' . $uri . '``';
                $counts = self::rows("SELECT COUNT(*) AS posts_7d,
                    COALESCE(SUM(`time` > $day), 0) AS posts_24h,
                    COALESCE(SUM(`time` > $day AND `thread` IS NOT NULL), 0) AS replies_24h,
                    COALESCE(SUM(`time` > $day AND `thread` IS NULL), 0) AS threads_24h,
                    COALESCE(SUM(`thread` IS NULL), 0) AS threads_7d
                    FROM $table WHERE `time` > $week AND `time` <= $now")[0];
                $data['boards'][] = ['uri' => $uri, 'title' => $info['title'], 'posts_7d' => (int)$counts['posts_7d']];
                foreach (['posts_24h', 'replies_24h', 'threads_24h', 'threads_7d'] as $field) {
                    $data['stats'][$field] += (int)$counts[$field];
                }
                $data['stats']['total_threads'] += (int)self::rows("SELECT COUNT(*) AS total FROM $table WHERE `thread` IS NULL")[0]['total'];
                if ($data['stats']['media_bytes'] !== null) {
                    $bytes = self::mediaBytes($table);
                    $data['stats']['media_bytes'] = $bytes === null ? null : $data['stats']['media_bytes'] + $bytes;
                }
                foreach (self::rows("SELECT LEAST(23, FLOOR((`time` - $day) / 3600)) AS hour, COUNT(*) AS total
                    FROM $table WHERE `time` > $day AND `time` <= $now GROUP BY hour") as $hour) {
                    $data['hours'][(int)$hour['hour']]['count'] += (int)$hour['total'];
                }

                $threads = self::rows("SELECT p.`id`, p.`subject`, LEFT(p.`body`, 4096) AS body, p.`time`, COALESCE(p.`bump`, p.`time`) AS bump, p.`slug`,
                    (SELECT COUNT(*) FROM $table r WHERE r.`thread` = p.`id`) AS replies
                    FROM $table p WHERE p.`thread` IS NULL ORDER BY bump DESC, p.`id` DESC LIMIT 8");
                foreach ($threads as $post) {
                    $text = self::text($post['body'], 360);
                    $data['threads'][] = [
                        'id' => (int)$post['id'], 'board' => $uri, 'board_name' => $info['title'],
                        'title' => self::text($post['subject'] ?: $post['body'], 100) ?: '#' . $post['id'],
                        'text' => $text, 'link' => self::link($post), 'time' => (int)$post['bump'],
                        'created_at' => (int)$post['time'], 'bump' => (int)$post['bump'], 'replies' => (int)$post['replies'],
                    ];
                }
                $replies = self::rows("SELECT p.`id`, p.`thread`, p.`subject`, LEFT(p.`body`, 4096) AS body, p.`time`,
                    op.`subject` AS thread_subject, LEFT(op.`body`, 4096) AS thread_body, op.`slug`
                    FROM $table p JOIN $table op ON op.`id` = p.`thread` AND op.`thread` IS NULL
                    WHERE p.`thread` IS NOT NULL ORDER BY p.`time` DESC, p.`id` DESC LIMIT 8");
                foreach ($replies as $post) {
                    $data['replies'][] = [
                        'id' => (int)$post['id'], 'thread' => (int)$post['thread'], 'board' => $uri, 'board_name' => $info['title'],
                        'title' => self::text($post['subject'] ?: $post['body'], 100) ?: '#' . $post['id'],
                        'text' => self::text($post['body'], 360), 'link' => self::link($post), 'time' => (int)$post['time'],
                        'thread_title' => self::text($post['thread_subject'] ?: $post['thread_body'], 100) ?: '#' . $post['thread'],
                    ];
                }
            }
        } finally {
            [$config, $board, $build_pages, $locale] = $saved;
            if ($locale !== null && $current_locale !== $locale) {
                $current_locale = $locale;
                init_locale($locale);
            }
        }

        foreach (['threads', 'replies'] as $kind) {
            usort($data[$kind], fn($a, $b) => $b['time'] <=> $a['time'] ?: strcmp($a['board'], $b['board']) ?: $b['id'] <=> $a['id']);
            $data[$kind] = array_slice($data[$kind], 0, 8);
        }
        $data['stats']['peak_hour'] = max(array_column($data['hours'], 'count'));
        return $data;
    }

    private static function rows(string $sql): array {
        $query = query($sql);
        if (!$query) {
            throw new RuntimeException('Could not read homepage activity.');
        }
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function mediaBytes(string $table): ?int {
        $bytes = 0;
        $last = 0;
        do {
            $rows = self::rows("SELECT `id`, `files` FROM $table WHERE `num_files` > 0 AND `id` > $last ORDER BY `id` LIMIT 250");
            foreach ($rows as $row) {
                $last = (int)$row['id'];
                $files = json_decode($row['files'] ?? '[]', true);
                if (!is_array($files)) {
                    return null;
                }
                foreach ($files as $file) {
                    if (isset($file['file']) && $file['file'] !== 'deleted') {
                        $bytes += max(0, (int)($file['size'] ?? 0));
                    }
                }
            }
        } while (count($rows) === 250);
        return $bytes;
    }

    private static function text(?string $html, int $limit): string {
        $text = html_entity_decode(strip_tags(preg_replace('~<br\s*/?>|</(?:p|div|li)>~i', ' ', $html ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        return mb_strlen($text, 'UTF-8') > $limit ? mb_substr($text, 0, $limit, 'UTF-8') . '…' : $text;
    }

    private static function link(array $post): string {
        global $config, $board;
        return $config['root'] . $board['dir'] . $config['dir']['res'] . link_for($post, false, false, ['slug' => $post['slug']]) . '#' . $post['id'];
    }
}
