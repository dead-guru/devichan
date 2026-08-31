USE devichan_e2e;

DELETE FROM `boards`;
INSERT INTO `boards` (`uri`, `title`, `subtitle`) VALUES
    ('b', 'Random', 'Public test board'),
    ('sec', 'Secret', 'Password-protected test board');

DELETE FROM `mods`;
INSERT INTO `mods` (`id`, `username`, `password`, `version`, `type`, `boards`) VALUES
    (1, 'admin', 'cedad442efeef7112fed0f50b011b2b9bf83f6898082f995f69dd7865ca19fb7', '4a44c6c55df862ae901b413feecb0d49', 30, '*');

CREATE TABLE IF NOT EXISTS `posts_b` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `thread` int(11) DEFAULT NULL,
    `subject` varchar(100) DEFAULT NULL,
    `email` varchar(30) DEFAULT NULL,
    `name` varchar(35) DEFAULT NULL,
    `trip` varchar(15) DEFAULT NULL,
    `capcode` varchar(50) DEFAULT NULL,
    `body` text NOT NULL,
    `body_nomarkup` text,
    `time` int(11) NOT NULL,
    `bump` int(11) DEFAULT NULL,
    `files` text DEFAULT NULL,
    `num_files` int(11) DEFAULT 0,
    `filehash` text CHARACTER SET ascii,
    `password` varchar(20) DEFAULT NULL,
    `ip` varchar(39) CHARACTER SET ascii NOT NULL,
    `sticky` int(1) NOT NULL,
    `locked` int(1) NOT NULL,
    `cycle` int(1) NOT NULL,
    `sage` int(1) NOT NULL,
    `embed` text,
    `slug` varchar(256) DEFAULT NULL,
    UNIQUE KEY `id` (`id`),
    KEY `thread_id` (`thread`, `id`),
    KEY `filehash` (`filehash`(40)),
    KEY `time` (`time`),
    KEY `ip` (`ip`),
    KEY `list_threads` (`thread`, `sticky`, `bump`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `posts_sec` LIKE `posts_b`;

INSERT INTO `posts_b`
    (`id`, `thread`, `subject`, `name`, `body`, `body_nomarkup`, `time`, `bump`, `password`, `ip`, `sticky`, `locked`, `cycle`, `sage`, `slug`)
VALUES
    (1, NULL, 'Seed thread', 'Anonymous', 'Seed public thread', 'Seed public thread', UNIX_TIMESTAMP() - 120, UNIX_TIMESTAMP() - 60, 'postpass', '127.0.0.10', 0, 0, 0, 0, 'seed-thread'),
    (2, 1, NULL, 'Anonymous', 'Seed reply >>1', 'Seed reply >>1', UNIX_TIMESTAMP() - 60, NULL, 'replypass', '127.0.0.11', 0, 0, 0, 0, NULL);

UPDATE `posts_b`
SET `files` = '[{"name":"seed-op.jpg","filename":"seed-op.jpg","file":"1700000000001.jpg","thumb":"1700000000001.png","size":12345,"width":640,"height":480,"thumbwidth":160,"thumbheight":120}]',
    `num_files` = 1
WHERE `id` = 1;

UPDATE `posts_b`
SET `files` = '[{"name":"seed-reply.png","filename":"seed-reply.png","file":"1700000000002.png","thumb":"1700000000002.png","size":6789,"width":480,"height":640,"thumbwidth":90,"thumbheight":120},{"name":"seed-notes.txt","filename":"seed-notes.txt","file":"1700000000003.txt","thumb":"file","size":24}]',
    `num_files` = 2
WHERE `id` = 2;

INSERT INTO `posts_sec`
    (`id`, `thread`, `subject`, `name`, `body`, `body_nomarkup`, `time`, `bump`, `password`, `ip`, `sticky`, `locked`, `cycle`, `sage`, `slug`)
VALUES
    (1, NULL, 'Secret seed', 'Anonymous', 'Secret board content', 'Secret board content', UNIX_TIMESTAMP() - 60, UNIX_TIMESTAMP() - 60, 'secretpass', '127.0.0.12', 0, 0, 0, 0, 'secret-seed');

DELETE FROM `theme_settings`;
INSERT INTO `theme_settings` (`theme`, `name`, `value`) VALUES
    ('index', NULL, NULL),
    ('index', 'icon', '../templates/themes/index/hikichanIcon.png'),
    ('index', 'title', 'DeVichan E2E'),
    ('index', 'subtitle', 'Deterministic integration fixture'),
    ('index', 'description', 'Local end-to-end test site.'),
    ('index', 'imageofnow', '../templates/themes/index/hotweels.jpg'),
    ('index', 'quoteofnow', 'Fixture quote'),
    ('index', 'videoofnow', 'https://www.youtube.com/embed/zndkMAHKjNM'),
    ('index', 'no_recent', '5'),
    ('index', 'exclude', 'sec'),
    ('index', 'limit_images', '3'),
    ('index', 'limit_posts', '30'),
    ('index', 'html', 'index.html'),
    ('index', 'css', 'index.css'),
    ('index', 'basecss', 'index.css'),
    ('catalog', NULL, NULL),
    ('catalog', 'title', 'Catalog'),
    ('catalog', 'boards', 'b'),
    ('catalog', 'update_on_posts', '1'),
    ('catalog', 'use_tooltipster', '1'),
    ('recent', NULL, NULL),
    ('recent', 'title', 'Recent Posts'),
    ('recent', 'exclude', 'sec'),
    ('recent', 'limit_images', '3'),
    ('recent', 'limit_posts', '30'),
    ('recent', 'html', 'recent.html'),
    ('recent', 'css', 'recent.css'),
    ('recent', 'basecss', 'recent.css'),
    ('sitemap', NULL, NULL),
    ('sitemap', 'path', 'sitemap.xml'),
    ('sitemap', 'url', 'http://localhost/'),
    ('sitemap', 'changefreq', 'hourly'),
    ('sitemap', 'regen_time', '0'),
    ('sitemap', 'boards', 'b');

INSERT INTO `news` (`id`, `name`, `time`, `subject`, `body`) VALUES
    (1, 'admin', UNIX_TIMESTAMP() - 300, 'Seed news', 'Seed news body');
INSERT INTO `noticeboard` (`id`, `mod`, `time`, `subject`, `body`) VALUES
    (1, 1, UNIX_TIMESTAMP() - 300, 'Seed notice', 'Seed notice body');
INSERT INTO `pms` (`id`, `sender`, `to`, `message`, `time`, `unread`) VALUES
    (1, 1, 1, 'Seed private message', UNIX_TIMESTAMP() - 300, 1);
INSERT INTO `reports` (`id`, `time`, `ip`, `board`, `post`, `reason`) VALUES
    (1, UNIX_TIMESTAMP() - 300, '127.0.0.13', 'b', 1, 'Seed report');
INSERT INTO `ip_notes` (`id`, `ip`, `mod`, `time`, `body`) VALUES
    (1, '127.0.0.10', 1, UNIX_TIMESTAMP() - 300, 'Seed IP note');
INSERT INTO `pages` (`id`, `board`, `name`, `title`, `type`, `content`) VALUES
    (1, NULL, 'seed-page', 'Seed page', 'html', '<p>Seed page body</p>');
INSERT INTO `bans`
    (`id`, `ipstart`, `ipend`, `created`, `expires`, `board`, `creator`, `reason`, `seen`, `post`)
VALUES
    (1, INET6_ATON('192.0.2.10'), NULL, UNIX_TIMESTAMP() - 300, UNIX_TIMESTAMP() + 86400, 'b', 1, 'Seed ban', 0, NULL);
INSERT INTO `ban_appeals` (`id`, `ban_id`, `time`, `message`, `denied`) VALUES
    (1, 1, UNIX_TIMESTAMP() - 200, 'Seed appeal', 0);
