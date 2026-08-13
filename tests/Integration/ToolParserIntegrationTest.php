<?php

declare(strict_types=1);

namespace DevichanE2E\Integration;

use PHPUnit\Framework\TestCase;

final class ToolParserIntegrationTest extends TestCase
{
    private string $directory = 'tests/_output/integration-parsers';
    private string $originalIncludePath;

    protected function setUp(): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
        $this->originalIncludePath = get_include_path();
        set_include_path('tools/inc/lib/jsgettext' . PATH_SEPARATOR . $this->originalIncludePath);
        require_once 'tools/inc/lib/jsgettext/JSParser.php';
        require_once 'tools/inc/lib/jsgettext/PoeditParser.php';
    }

    protected function tearDown(): void
    {
        set_include_path($this->originalIncludePath);
    }

    public function testJavascriptParserIgnoresCommentsAndPreservesRegexAndQuotes(): void
    {
        $javascript = $this->directory . '/messages.js';
        file_put_contents($javascript, <<<'JS'
const matcher = /https?:\/\/example\.com\/path/i;
const one = _("Hello world");
const two = gettext('It\'s translated');
// _("Ignored line")
/* gettext('Ignored block') */
JS);

        $parser = new \JSParser($javascript, ['_', 'gettext']);
        self::assertSame(['Hello world', "It's translated"], $parser->parse());
    }

    public function testPoeditParserParsesMergesSerializesAndSavesCatalogs(): void
    {
        $catalog = $this->directory . '/messages.po';
        file_put_contents($catalog, <<<'PO'
msgid ""
msgstr ""
"Language: uk\\n"

#: app.js:1
#, fuzzy
msgid "Hello world"
msgstr "Привіт"

#: app.js:2
msgid "Quoted \"value\""
msgstr ""
PO);

        $parser = new \PoeditParser($catalog);
        $parser->parse();
        self::assertStringContainsString('Language: uk', $parser->getHeader());
        self::assertArrayHasKey('Hello world', $parser->getStrings());
        self::assertTrue($parser->getStrings()['Hello world']->fuzzy);

        $parser->merge(['Hello world', 'New message']);
        self::assertArrayHasKey('New message', $parser->getStrings());
        self::assertJson($parser->getJSON());
        self::assertStringContainsString('#, fuzzy', (string) $parser->getStrings()['Hello world']);

        $json = $this->directory . '/messages.js';
        $saved = $this->directory . '/saved.po';
        self::assertTrue($parser->toJSON($json, 'translations'));
        self::assertTrue($parser->save($saved));
        self::assertStringContainsString('translations = ', (string) file_get_contents($json));
        self::assertStringContainsString('New message', (string) file_get_contents($saved));
    }
}
