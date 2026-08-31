<?php

declare(strict_types=1);

namespace DevichanE2E\Http;

use DevichanE2E\Support\AdminSession;
use DevichanE2E\Support\HttpTester;

final class ModeratorConfigEditorCest
{
    use AdminSession;

    public function _after(HttpTester $I): void
    {
        $I->deleteHeader('X-E2E-Config-Editor');
        $boardConfig = '/var/www/b/config.php';
        if (is_file($boardConfig)) {
            unlink($boardConfig);
        }
    }

    public function readonlyGlobalEditorReturnsManualConfiguration(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $I->amOnPage('/mod.php?/config');
        $I->seeResponseCodeIs(200);
        $I->submitForm('form[method="post"]', [
            'cf_global_message' => 'E2E cannot write global fixture',
            'save' => 'Save changes',
        ]);
        $I->seeResponseCodeIs(200);
        $I->see('Cannot write to file');
        $I->see('proceed with these changes manually');
    }

    public function phpBoardEditorRejectsInvalidSyntaxAndAcceptsValidSyntax(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $I->haveHttpHeader('X-E2E-Config-Editor', 'php');
        $I->amOnPage('/mod.php?/config/b');
        $I->seeResponseCodeIs(200);
        $I->seeElement('textarea[name="code"]');

        $I->submitForm('form[method="post"]', [
            'code' => '<?php this is invalid php',
            'save' => 'Save changes',
        ]);
        $I->seeResponseCodeIs(400);
        $I->see('syntax');

        $I->amOnPage('/mod.php?/config/b');
        $I->submitForm('form[method="post"]', [
            'code' => "<?php\n\n\$config['title'] = 'E2E temporary board title';\n",
            'save' => 'Save changes',
        ]);
        $I->seeResponseCodeIs(200);
        $I->assertStringContainsString(
            'E2E temporary board title',
            (string) file_get_contents('/var/www/b/config.php'),
        );
    }

    public function readonlyGlobalPhpEditorRendersWithoutWritingSecrets(HttpTester $I): void
    {
        $this->loginAsAdmin($I);
        $I->haveHttpHeader('X-E2E-Config-Editor', 'php');
        $I->amOnPage('/mod.php?/config');
        $I->seeResponseCodeIs(200);
        $I->see('does not have the required permissions');
        $I->seeElement('textarea[readonly]');
    }
}
