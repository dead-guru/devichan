<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PHPUnit\Framework\TestCase;

final class TemplateIntegrationTest extends TestCase
{
    public function testPostDatesKeepStrftimeFormatsAndUtcRegardlessOfServerTimezone(): void
    {
        load_twig();
        $locale = setlocale(LC_TIME, '0');
        $timezone = date_default_timezone_get();
        try {
            setlocale(LC_TIME, 'C');
            date_default_timezone_set('Europe/Kyiv');
            $date = (new \DateTimeImmutable('2026-10-21 14:23:45 +00:00'))->getTimestamp();
            self::assertSame('10/21/26 (Wed) 14:23:45', twig_date_filter($date, '%m/%d/%y (%a) %H:%M:%S'));
            self::assertSame('2026-10-21T14:23:45', twig_date_filter($date, '%Y-%m-%dT%H:%M:%S'));
            self::assertSame('+0000 GMT %', twig_date_filter($date, '%z %Z %%'));
            self::assertNotFalse(setlocale(LC_TIME, 'C.utf8'));
            self::assertSame('Wed', twig_date_filter($date, '%a'));
        } finally {
            setlocale(LC_TIME, $locale);
            date_default_timezone_set($timezone);
        }
    }

    public function testLocaleCompositeDatesKeepTheirExistingFormat(): void
    {
        $locale = setlocale(LC_TIME, '0');
        $date = (new \DateTimeImmutable('2026-10-21 14:23:45 +00:00'))->getTimestamp();
        $formats = [
            'C' => 'Wed Oct 21 14:23:45 2026|10/21/26|14:23:45',
            'C.utf8' => 'Wed Oct 21 14:23:45 2026|10/21/26|14:23:45',
            'en_US.UTF-8' => 'Wed 21 Oct 2026 02:23:45 PM GMT|10/21/2026|02:23:45 PM',
            'uk_UA.UTF-8' => 'ср, 21-жов-2026 14:23:45 +0000|21.10.26|14:23:45',
        ];
        try {
            foreach ($formats as $name => $expected) {
                self::assertNotFalse(setlocale(LC_TIME, $name));
                self::assertSame($expected, twig_date_filter($date, '%c|%x|%X'), $name);
                self::assertSame($expected, twig_date_filter($date, '%Ec|%Ex|%EX'), $name);
                self::assertSame('20|26|2026|21|10|26', twig_date_filter($date, '%EC|%Ey|%EY|%Od|%Om|%Oy'), $name);
                self::assertSame('10/21/26|%c|%Ec', twig_date_filter($date, '%D|%%c|%%Ec'), $name);
            }
            self::assertSame('02:23:45 ||', twig_date_filter($date, '%r|%p|%P'));
            self::assertSame('ср|середа|жов|жовтня|жов', twig_date_filter($date, '%a|%A|%b|%B|%h'));
            self::assertNotFalse(setlocale(LC_TIME, 'pl_PL.UTF-8'));
            self::assertSame('śro|środa|paź|października|paź', twig_date_filter($date, '%a|%A|%b|%B|%h'));
        } finally {
            setlocale(LC_TIME, $locale);
        }
    }

    public function testLocaleCompositeDatesWithAlternativeDigitsRemainRenderable(): void
    {
        $locale = setlocale(LC_TIME, '0');
        $date = (new \DateTimeImmutable('2026-10-21 14:23:45 +00:00'))->getTimestamp();
        try {
            foreach (['fa_IR.UTF-8', 'ja_JP.UTF-8', 'th_TH.UTF-8'] as $name) {
                self::assertNotFalse(setlocale(LC_TIME, $name));
                foreach (['%c', '%x', '%X', '%Ec', '%Ex', '%EX'] as $format) {
                    self::assertNotSame('', twig_date_filter($date, $format), $name . ' ' . $format);
                }
            }
        } finally {
            setlocale(LC_TIME, $locale);
        }
    }

    public function testTranslationsRenderVariablesPluralsAndNotes(): void
    {
        global $twig;

        load_twig();

        $greeting = $twig->createTemplate('{% trans %}Hello {{ name }}{% endtrans %}');
        self::assertSame('Hello Alice', $greeting->render(['name' => 'Alice']));

        $replies = $twig->createTemplate(
            '{% trans %}One reply{% plural count %}{{ count }} replies{% endtrans %}',
        );
        self::assertSame('One reply', $replies->render(['count' => 1]));
        self::assertSame('2 replies', $replies->render(['count' => 2]));

        self::assertSame('Reply', $twig->createTemplate('{% trans "Reply" %}')->render());
        self::assertSame(
            'Open',
            $twig->createTemplate('{% trans %}Open{% notes %}moderator action{% endtrans %}')->render(),
        );
    }
}
