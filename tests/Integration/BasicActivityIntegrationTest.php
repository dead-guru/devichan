<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PHPUnit\Framework\TestCase;

final class BasicActivityIntegrationTest extends TestCase
{
    private const BOARDS = ['hpa', 'hpb', 'hpprivate', 'hpunlisted'];

    private array $originalConfig;
    private array $settings;
    private int $now;
    private string $outputDirectory = 'tests/_output/integration-homepage/';

    protected function setUp(): void
    {
        global $config;

        require_once 'templates/themes/basic/theme.php';
        require_once 'templates/themes/basic/activity.php';

        $this->originalConfig = $config;
        $this->now = time();
        $this->settings = [
            'active_homepage' => '1',
            'activity_boards' => 'hpa hpb hpprivate missing',
            'file' => 'homepage.html',
            'no_recent' => '1',
        ];
        $config['cache']['enabled'] = 'php';
        $config['cache']['prefix'] = 'homepage-integration:';
        $config['secret_boards']['hpprivate'] = ['not-a-public-board'];
        $config['root'] = '/';
        $config['dir']['home'] = $this->outputDirectory;
        $config['purge'] = [];
        if (!is_dir($this->outputDirectory)) {
            mkdir($this->outputDirectory, 0777, true);
        }
        \Cache::init();

        $board = prepare('INSERT INTO ``boards`` (`uri`, `title`, `subtitle`) VALUES (:uri, :title, :subtitle)');
        foreach (self::BOARDS as $uri) {
            query(sprintf('CREATE TABLE ``posts_%s`` LIKE ``posts_b``', $uri));
            $board->execute(['uri' => $uri, 'title' => strtoupper($uri), 'subtitle' => 'Homepage integration']);
        }

        $this->insert('hpa', 1, null, -8 * 86400, -20, 'Old topic', 'Old topic body', [
            ['file' => 'old.png', 'size' => 100],
            ['file' => 'deleted', 'size' => 9000],
        ]);
        $this->insert('hpa', 2, 1, -120, null, '', 'First recent reply');
        $this->insert('hpa', 3, 1, -60, null, '', 'Second recent reply');
        $this->insert('hpa', 4, null, -2 * 86400, -2 * 86400, 'Two days old', 'Two days old body');
        $this->insert('hpa', 5, null, -200, -200, '', 'Fallback title from body');
        $this->insert('hpa', 6, 5, -40, null, '', 'Sage reply', [['file' => 'reply.png', 'size' => 200]]);
        $this->insert('hpb', 1, null, -300, -300, 'Beta topic', 'Beta body');
        $this->insert('hpb', 2, 1, -10, null, '', 'Latest reply <script>alert(1)</script> & text');
        $this->insert('hpb', 3, null, -9 * 86400, -9 * 86400, 'Very old topic', 'Old body');
        $this->insert('hpprivate', 1, null, -1, -1, 'Private subject', 'Private content');
        $this->insert('hpunlisted', 1, null, -1, -1, 'Unlisted subject', 'Unlisted content');
    }

    protected function tearDown(): void
    {
        global $config;

        $delete = prepare('DELETE FROM ``boards`` WHERE `uri` = :uri');
        foreach (self::BOARDS as $uri) {
            query(sprintf('DROP TABLE IF EXISTS ``posts_%s``', $uri));
            $delete->execute(['uri' => $uri]);
        }
        $config = $this->originalConfig;
        \Cache::init();
        foreach (glob($this->outputDirectory . '*') as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testCountsComeFromAllowedPublicBoardsAndRealTimeWindows(): void
    {
        $data = \BasicActivity::snapshot($this->settings, true);

        self::assertSame(['hpa', 'hpb'], array_column($data['boards'], 'uri'));
        self::assertSame([5, 2], array_column($data['boards'], 'posts_7d'));
        self::assertSame(6, $data['stats']['posts_24h']);
        self::assertSame(4, $data['stats']['replies_24h']);
        self::assertSame(2, $data['stats']['threads_24h']);
        self::assertSame(3, $data['stats']['threads_7d']);
        self::assertSame(5, $data['stats']['total_threads']);
        self::assertSame(300, $data['stats']['media_bytes']);
        self::assertCount(24, $data['hours']);
        self::assertSame(6, array_sum(array_column($data['hours'], 'count')));
        self::assertLessThanOrEqual(time(), $data['generated_at']);

        $serialized = json_encode($data, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('Private', $serialized);
        self::assertStringNotContainsString('Unlisted', $serialized);
        self::assertStringNotContainsString('127.0.0.', $serialized);
        self::assertStringNotContainsString('not-public-password', $serialized);
    }

    public function testLatestRepliesAreSeparateFromBumpedThreadsAndKeepReplyAnchors(): void
    {
        $data = \BasicActivity::snapshot($this->settings, true);

        self::assertSame([1, 5, 1, 4, 3], array_column($data['threads'], 'id'));
        self::assertSame(['hpa', 'hpa', 'hpb', 'hpa', 'hpb'], array_column($data['threads'], 'board'));
        self::assertSame(2, $data['threads'][0]['replies']);
        self::assertSame('Fallback title from body', $data['threads'][1]['title']);
        self::assertSame($this->now - 20, $data['threads'][0]['time']);
        self::assertSame($this->now - 8 * 86400, $data['threads'][0]['created_at']);
        self::assertSame([2, 6, 3, 2], array_column($data['replies'], 'id'));
        self::assertSame(['hpb', 'hpa', 'hpa', 'hpa'], array_column($data['replies'], 'board'));
        self::assertSame('/hpb/res/1.html#2', $data['replies'][0]['link']);
        self::assertSame('Beta topic', $data['replies'][0]['thread_title']);
    }

    public function testUnconfiguredAndInvalidAllowlistNeverFallsBackToEveryBoard(): void
    {
        foreach (['', 'missing hpprivate', 'hpa` UNION SELECT secret'] as $allowlist) {
            $settings = array_replace($this->settings, ['activity_boards' => $allowlist]);
            $data = \BasicActivity::snapshot($settings, true);

            self::assertSame([], $data['boards']);
            self::assertSame([], $data['threads']);
            self::assertSame([], $data['replies']);
            self::assertSame(0, $data['stats']['posts_24h']);
        }
    }

    public function testSnapshotReusesFreshCacheAndRefreshesAfterTheEnvelopeExpires(): void
    {
        $before = \BasicActivity::snapshot($this->settings, true);
        $this->insert('hpa', 7, 1, -5, null, '', 'A reply after the snapshot');

        self::assertSame($before, \BasicActivity::snapshot($this->settings));

        [$key, $envelope] = $this->cachedSnapshot();
        $envelope['generated_at'] = time() - 299;
        \Cache::set($key, $envelope);
        self::assertSame($before, \BasicActivity::snapshot($this->settings));

        $envelope['generated_at'] = time() - 301;
        \Cache::set($key, $envelope);
        $after = \BasicActivity::snapshot($this->settings);

        self::assertSame(7, $after['stats']['posts_24h']);
        self::assertSame(7, $after['replies'][0]['id']);
        self::assertGreaterThanOrEqual($before['generated_at'], $after['generated_at']);
    }

    public function testInvalidAndFutureCacheEnvelopesAreRebuilt(): void
    {
        \BasicActivity::snapshot($this->settings, true);
        [$key, $envelope] = $this->cachedSnapshot();
        $this->insert('hpa', 7, 1, -5, null, '', 'A reply after the snapshot');

        foreach ([
            ['generated_at' => time(), 'data' => ['incomplete' => true]],
            array_replace($envelope, ['generated_at' => time() + 60]),
            ['generated_at' => 'not a timestamp', 'data' => $envelope['data']],
        ] as $invalid) {
            \Cache::set($key, $invalid);
            $data = \BasicActivity::snapshot($this->settings);

            self::assertSame(7, $data['stats']['posts_24h']);
            self::assertSame(7, $data['replies'][0]['id']);
        }
    }

    public function testMakingABoardPrivateCannotReuseItsPublicSnapshot(): void
    {
        global $config;

        $before = \BasicActivity::snapshot($this->settings, true);
        self::assertSame('hpb', $before['replies'][0]['board']);
        $config['secret_boards']['hpb'] = ['newly-private'];

        $after = \BasicActivity::snapshot($this->settings);

        self::assertSame(['hpa'], array_column($after['boards'], 'uri'));
        self::assertSame(4, $after['stats']['posts_24h']);
        self::assertNotContains('hpb', array_column($after['threads'], 'board'));
        self::assertNotContains('hpb', array_column($after['replies'], 'board'));
    }

    public function testListsStayBoundedAndSkipRepliesWithNoParent(): void
    {
        for ($id = 10; $id < 22; ++$id) {
            $this->insert('hpa', $id, null, -$id, -$id, 'Extra topic ' . $id, 'Extra topic body');
            $this->insert('hpb', $id, 1, -$id, null, '', 'Extra reply ' . $id);
        }
        $this->insert('hpa', 99, 999, -1, null, '', 'Orphan reply');

        $data = \BasicActivity::snapshot($this->settings, true);

        self::assertCount(8, $data['threads']);
        self::assertCount(8, $data['replies']);
        self::assertNotContains(99, array_column($data['replies'], 'id'));
    }

    public function testServerRenderedPageIncludesBothFeedsNewsLogoAndSeoWithoutJavascript(): void
    {
        global $config;

        $config['locale'] = 'uk_UA.UTF-8';
        $config['timezone'] = 'Europe/Kyiv';
        $settings = array_replace($this->settings, [
            'head_title' => 'Дедач — українська імеджборда',
            'title' => 'Дедач',
            'description' => 'Анонімний форум українською без реєстрації.',
            'canonical_url' => 'https://deada.ch/',
            'logo' => "/static/first.png?v=1&style=\"pixel\"\n/static/second.png",
            'board_links' => "hpa Bread (18+)\nhpb Техніка",
        ]);
        $html = \Basic::homepage($settings, true);
        $page = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);

        self::assertSame('uk', $page->documentElement->getAttribute('lang'));
        self::assertSame($settings['head_title'], $page->querySelector('title')->textContent);
        self::assertSame($settings['description'], $page->querySelector('meta[name="description"]')->getAttribute('content'));
        self::assertSame('https://deada.ch/', $page->querySelector('link[rel="canonical"]')->getAttribute('href'));
        self::assertNull($page->querySelector('meta[name="robots"]'));
        self::assertSame(1, $page->querySelectorAll('h1')->length);
        self::assertSame('Дедач', $page->querySelector('h1')->textContent);
        self::assertSame(2, $page->querySelectorAll('#boards li a')->length);
        self::assertSame('18+', $page->querySelector('#boards small')->textContent);
        self::assertSame(5, $page->querySelectorAll('#feed-threads .feed-link')->length);
        self::assertSame(4, $page->querySelectorAll('#feed-replies .feed-link')->length);
        self::assertFalse($page->querySelector('#feed-threads')->hasAttribute('hidden'));
        self::assertFalse($page->querySelector('#feed-replies')->hasAttribute('hidden'));
        self::assertTrue($page->querySelector('.feed-switch')->hasAttribute('hidden'));
        self::assertSame('/hpb/res/1.html#2', $page->querySelector('#feed-replies .feed-link')->getAttribute('href'));
        $replyTime = $page->querySelector('#feed-replies time');
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:?\d{2}$/', $replyTime->getAttribute('datetime'));
        self::assertSame($this->now - 10, (new \DateTimeImmutable($replyTime->getAttribute('datetime')))->getTimestamp());
        self::assertSame(gmdate('d.m · H:i', $this->now - 10), $replyTime->textContent);
        self::assertSame(gmdate('d.m.Y H:i', $this->now - 10) . ' GMT', $replyTime->getAttribute('title'));
        self::assertStringContainsString('Seed news', $page->querySelector('.news-item summary')->textContent);
        self::assertStringContainsString('Seed news body', $page->querySelector('.news-body')->textContent);
        self::assertTrue($page->querySelector('.news-item')->hasAttribute('open'));
        $newsTime = (int)query('SELECT `time` FROM ``news`` ORDER BY `time` DESC LIMIT 1')->fetchColumn();
        self::assertSame(gmdate('Y-m-d', $newsTime), $page->querySelector('.news-item time')->getAttribute('datetime'));
        self::assertSame(gmdate('d.m.Y', $newsTime), $page->querySelector('.news-item time')->textContent);
        self::assertMatchesRegularExpression('/^\d{2}\.\d{2} \d{2}:\d{2}–\d{2}:\d{2}/', $page->querySelector('.sparkline span')->getAttribute('title'));
        self::assertSame(1, $page->querySelectorAll('script')->length);
        self::assertSame('', trim($page->querySelector('script')->textContent));
        self::assertStringContainsString('<script>alert(1)</script>', $page->querySelector('#feed-replies')->textContent);
        self::assertStringNotContainsString('Private content', $html);
        self::assertStringNotContainsString('not-public-password', $html);

        $logo = $page->querySelector('header img');
        self::assertSame('/static/first.png?v=1&style="pixel"', $logo->getAttribute('src'));
        self::assertSame(['/static/first.png?v=1&style="pixel"', '/static/second.png'], json_decode($logo->getAttribute('data-logos'), true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(3 * (int)$logo->getAttribute('height'), (int)$logo->getAttribute('width'));
        self::assertGreaterThan(0, (int)$logo->getAttribute('height'));
        self::assertMatchesRegularExpression('/active\.css\?v=[a-f0-9]{12}$/', $page->querySelector('link[rel="stylesheet"]')->getAttribute('href'));
    }

    public function testDeletionBuildForcesFreshDataAndPublishesCompleteHtml(): void
    {
        global $config;

        $settings = array_replace($this->settings, ['title' => 'Homepage fixture', 'board_links' => 'hpa Board A']);
        self::assertTrue(\Basic::build('all', $settings));
        $path = $this->outputDirectory . $settings['file'];
        self::assertStringContainsString('Latest reply', file_get_contents($path));
        chmod($path, 0640);
        query('DELETE FROM ``posts_hpb`` WHERE `id` = 2');

        self::assertSame(6, \BasicActivity::snapshot($settings)['stats']['posts_24h']);
        self::assertTrue(\Basic::build('post-delete', $settings));

        $html = file_get_contents($path);
        self::assertStringNotContainsString('Latest reply', $html);
        self::assertStringContainsString('</html>', $html);
        self::assertSame(5, \BasicActivity::snapshot($settings)['stats']['posts_24h']);
        clearstatcache(true, $path);
        self::assertSame(0640, fileperms($path) & 0777);
        self::assertSame([], glob($this->outputDirectory . '.homepage-*'));
    }

    public function testInterfaceUsesTheSiteLocaleAndKeepsInstanceLinksInOwnerContent(): void
    {
        global $config, $current_locale;

        $locale = setlocale(LC_ALL, '0');
        $currentLocale = $current_locale;
        $settings = array_replace($this->settings, [
            'title' => 'Fixture imageboard',
            'canonical_url' => 'https://example.org/',
            'board_links' => "hpa Board A\nhpb Board B\nhpunlisted Board without public statistics",
            'meta' => '<nav id="owner-links"><a href="ircs://chat.example.org:6697">IRC</a><a href="/custom-rules">Rules</a></nav>',
            'irc' => 'must-not-render.example:6697',
            'i2p' => 'http://must-not-render.example.i2p/',
            'irc_i2p' => 'must-not-render.example.i2p:6697',
        ]);

        try {
            foreach ([
                'en_US.UTF-8' => ['en', 'Boards', 'News', 'Latest replies', 'Pause metrics', 'posts / 24 h'],
                'uk_UA.UTF-8' => ['uk', 'Дошки', 'Новини', 'Останні відповіді', 'Призупинити рух метрик', 'постів / 24 год'],
            ] as $name => [$lang, $boards, $news, $replies, $pause, $posts]) {
                $config['locale'] = $current_locale = $name;
                init_locale($name);
                $html = \Basic::homepage($settings, true);
                $page = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);

                self::assertSame($lang, $page->documentElement->getAttribute('lang'));
                self::assertStringContainsString($boards, $page->querySelector('#boards-title')->textContent);
                self::assertSame($news, trim($page->querySelector('#news-title')->textContent));
                self::assertSame($replies, trim($page->querySelector('#feed-replies .feed-heading')->textContent));
                self::assertSame($pause, $page->querySelector('.ticker-toggle')->getAttribute('data-pause-label'));
                self::assertSame($posts, trim($page->querySelector('.metric-label')->textContent));
                self::assertSame('ircs://chat.example.org:6697', $page->querySelector('#owner-links a')->getAttribute('href'));
                self::assertNull($page->querySelector('a[href="/*/"]'));
                self::assertNull($page->querySelector('a[href="/recent.html"]'));
                self::assertNull($page->querySelector('a[href="/rules.html"]'));
                self::assertStringNotContainsString('must-not-render.example', $html);
                if ($lang === 'en') {
                    self::assertDoesNotMatchRegularExpression('/[\x{0400}-\x{04ff}]/u', $html);
                }
            }
        } finally {
            $current_locale = $currentLocale;
            setlocale(LC_ALL, $locale);
        }

        require 'templates/themes/basic/info.php';
        $fields = array_column($theme['config'], 'name');
        self::assertNotContains('irc', $fields);
        self::assertNotContains('i2p', $fields);
        self::assertNotContains('irc_i2p', $fields);
    }

    private function cachedSnapshot(): array
    {
        global $config;

        $property = new \ReflectionProperty(\Cache::class, 'cache');
        foreach ($property->getValue() as $key => $value) {
            if (is_array($value) && isset($value['generated_at'], $value['data']['stats'])) {
                return [substr($key, strlen($config['cache']['prefix'])), $value];
            }
        }
        self::fail('The snapshot was not stored in the configured cache backend.');
    }

    private function insert(string $uri, int $id, ?int $thread, int $time, ?int $bump, string $subject, string $body, array $files = []): void
    {
        $query = prepare(sprintf(
            'INSERT INTO ``posts_%s`` (`id`, `thread`, `subject`, `name`, `body`, `body_nomarkup`, `time`, `bump`, `files`, `num_files`, `ip`, `password`, `sticky`, `locked`, `cycle`, `sage`) '
            . 'VALUES (:id, :thread, :subject, :name, :body, :body_nomarkup, :time, :bump, :files, :num_files, :ip, :password, 0, 0, 0, 0)',
            $uri,
        ));
        $query->execute([
            'id' => $id,
            'thread' => $thread,
            'subject' => $subject,
            'name' => 'Fixture author',
            'body' => '<span class="quote">' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</span>',
            'body_nomarkup' => $body,
            'time' => $this->now + $time,
            'bump' => $bump === null ? null : $this->now + $bump,
            'files' => $files ? json_encode($files, JSON_THROW_ON_ERROR) : null,
            'num_files' => count($files),
            'ip' => '127.0.0.99',
            'password' => 'not-public-password',
        ]);
    }
}
