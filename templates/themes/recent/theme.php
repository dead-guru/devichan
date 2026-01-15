<?php
require 'info.php';

function recentposts_build($action, $settings, $board)
{
    // Possible values for $action:
    //	- all (rebuild everything, initialization)
    //	- news (news has been updated)
    //	- boards (board list changed)
    //	- post (a post has been made)
    //	- post-thread (a thread has been made)

    $b = new RecentPosts();
    $b->build($action, $settings);
}

// Wrap functions in a class so they don't interfere with normal Tinyboard operations
class RecentPosts
{
    public function build($action, $settings)
    {
        global $config;

        if ($action == 'all') {
            copy('templates/themes/recent/' . $settings['basecss'], $config['dir']['home'] . $settings['css']);
        }

        $this->excluded = explode(' ', $settings['exclude']);

        if ($action == 'all' || $action == 'post' || $action == 'post-thread' || $action == 'post-delete') {
            $action = generation_strategy('sb_recent', array());
            if ($action == 'delete') {
                file_unlink($config['dir']['home'] . $settings['html']);
            } elseif ($action == 'rebuild') {
                file_write($config['dir']['home'] . $settings['html'], $this->homepage($settings));
            }
        }
    }

    // Build news page
    public function homepage($settings)
    {
        global $config, $board;

        $recent_images = array();
        $recent_posts = array();
        $stats = array();

        $boards = listBoards();
        
        $query_images_parts = [];
        foreach ($boards as &$_board) {
            if (in_array($_board['uri'], $this->excluded)) continue;
            $query_images_parts[] = sprintf(
                "SELECT *, '%s' AS `board` FROM ``posts_%s`` WHERE `files` IS NOT NULL",
                $_board['uri'], $_board['uri']
            );
        }

        if (!empty($query_images_parts)) {
            $query = implode(' UNION ALL ', $query_images_parts) . ' ORDER BY `time` DESC LIMIT ' . (int)$settings['limit_images'];
            $query = query($query) or error(db_error());

            while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
                openBoard($post['board']);
                $files = json_decode($post['files']);

                if ($files[0]->file == 'deleted' || $files[0]->thumb == 'file') continue;

                $post['link'] = $config['root'] . $board['dir'] . $config['dir']['res'] . link_for($post) . '#' . $post['id'];

                if ($files[0]->thumb == 'spoiler') {
                    $tn_size = @getimagesize($config['spoiler_image']);
                    $post['src'] = $config['spoiler_image'];
                    $post['thumbwidth'] = $tn_size[0];
                    $post['thumbheight'] = $tn_size[1];
                } else {
                    $post['src'] = $config['uri_thumb'] . $files[0]->thumb;
                    $post['thumbwidth'] = $files[0]->thumbwidth;
                    $post['thumbheight'] = $files[0]->thumbheight;
                }
                $recent_images[] = $post;
            }
        }
        
        $query_threads_parts = [];
        foreach ($boards as &$_board) {
            if (in_array($_board['uri'], $this->excluded)) continue;
            $query_threads_parts[] = sprintf("SELECT *, '%s' AS `board` FROM ``posts_%s`` WHERE `thread` IS NULL", $_board['uri'], $_board['uri']);
        }

        if (!empty($query_threads_parts)) {
            $query = implode(' UNION ALL ', $query_threads_parts) . ' ORDER BY `bump` DESC LIMIT ' . (int)$settings['limit_posts'];
            $query = query($query) or error(db_error());

            while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
                openBoard($post['board']);
                $post['link'] = $config['root'] . $board['dir'] . $config['dir']['res'] . link_for($post) . '#' . $post['id'];
                $post['snippet'] = ($post['body'] != "") ? pm_snippet($post['body'], 120) : ($post['subject'] ? $post['subject'] : "<em>" . _("(no comment)") . "</em>");
                $post['board_name'] = $board['name'];
                $recent_posts[] = $post;
            }
        }
        
        $posts_all_parts = [];
        $posts_24h_parts = [];
        $threads_all_parts = [];
        $top_boards_parts = [];
        $files_parts = [];
        $unique_ips_all_parts = [];
        $unique_ips_24h_parts = [];

        $time_24h_ago = time() - 86400;
        $time_7d_ago = time() - (86400 * 7);

        foreach ($boards as &$_board) {
            if (in_array($_board['uri'], $this->excluded)) continue;

            $tbl = "``posts_{$_board['uri']}``";
            $posts_all_parts[] = sprintf("SELECT MAX(`id`) AS `top` FROM %s", $tbl);
            $posts_24h_parts[] = sprintf("SELECT `id` FROM %s WHERE `time` > %d", $tbl, $time_24h_ago);
            $threads_all_parts[] = sprintf("SELECT `id` FROM %s WHERE `thread` IS NULL", $tbl);
            $top_boards_parts[] = sprintf("SELECT '%s' AS `board_uri`, `id` FROM %s WHERE `time` > %d", $_board['uri'], $tbl, $time_7d_ago);
            $files_parts[] = sprintf("SELECT `files` FROM %s WHERE `num_files` > 0", $tbl);
            $unique_ips_all_parts[] = sprintf("SELECT `ip` FROM %s", $tbl);
            $unique_ips_24h_parts[] = sprintf("SELECT `ip` FROM %s WHERE `time` > %d", $tbl, $time_24h_ago);
        }

        // Total posts (загалом)
        $query = 'SELECT SUM(`top`) FROM (' . implode(' UNION ALL ', $posts_all_parts) . ') AS `posts_all`';
        $stats['total_posts'] = number_format(query($query)->fetchColumn());

        // Total threads (загалом)
        $query = 'SELECT COUNT(`id`) FROM (' . implode(' UNION ALL ', $threads_all_parts) . ') AS `threads_all`';
        $stats['total_threads'] = number_format(query($query)->fetchColumn());

        // Posts (24h)
        $query = 'SELECT COUNT(`id`) FROM (' . implode(' UNION ALL ', $posts_24h_parts) . ') AS `posts_24h`';
        $stats['posts_today'] = number_format(query($query)->fetchColumn());

        // Unique posters (All time)
        $query = 'SELECT COUNT(DISTINCT(`ip`)) FROM (' . implode(' UNION ALL ', $unique_ips_all_parts) . ') AS `posts_all`';
        $stats['unique_posters'] = number_format(query($query)->fetchColumn());

        // Unique posters (24h)
        $query = 'SELECT COUNT(DISTINCT(`ip`)) FROM (' . implode(' UNION ALL ', $unique_ips_24h_parts) . ') AS `posts_24h`';
        $stats['unique_posters_today'] = number_format(query($query)->fetchColumn());
        
        $query = 'SELECT DISTINCT(`files`) FROM (' . implode(' UNION ALL ', $files_parts) . ') AS `posts_files`';
        $files = query($query)->fetchAll();
        $stats['active_content'] = 0;
        foreach ($files as &$file) {
            preg_match_all('/"size":([0-9]*)/', $file[0], $matches);
            $stats['active_content'] += array_sum($matches[1]);
        }
        
        $stats['top_boards'] = [];
        $query_top_boards = 'SELECT `board_uri`, COUNT(`id`) AS `post_count` FROM (' . implode(' UNION ALL ', $top_boards_parts) . ') AS `top_boards_union` GROUP BY `board_uri` ORDER BY `post_count` DESC LIMIT 5';
        $top_boards_q = query($query_top_boards)->fetchAll(PDO::FETCH_ASSOC);

        $board_map = [];
        foreach ($boards as &$_board) { $board_map[$_board['uri']] = $_board['name']; }

        foreach ($top_boards_q as $row) {
            $stats['top_boards'][] = [
                'uri' => $row['board_uri'],
                'name' => $board_map[$row['board_uri']],
                'count' => number_format($row['post_count'])
            ];
        }
        
        $stats['latest_news'] = [];
        $query = query("SELECT `subject`, `body`, `time` FROM ``news`` ORDER BY `time` DESC LIMIT 5") or error(db_error());
        $stats['latest_news'] = $query->fetchAll(PDO::FETCH_ASSOC);
        
        $map_func = function ($post) {
            unset($post['ip'], $post['password'], $post['trip'], $post['capcode']);
            if (array_key_exists('files', $post) && $post['files'] !== null) {
                try {
                    $post['files'] = json_decode($post['files'], true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) { $post['files'] = []; }
            }
            return $post;
        };
        $recent_posts = array_map($map_func, $recent_posts);
        $recent_images = array_map($map_func, $recent_images);
        
        file_write(
            'recent.json',
            json_encode([
                'recent_images' => $recent_images,
                'recent_posts' => $recent_posts,
                'stats' => $stats
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
        );
        
        return Element('themes/recent/recent.html', array(
            'settings' => $settings,
            'config' => $config,
            'boardlist' => createBoardlist(),
            'recent_images' => $recent_images,
            'recent_posts' => $recent_posts,
            'stats' => $stats
        ));
    }
}

?>