<?php

declare(strict_types=1);

namespace DevichanE2E\Browser;

use DevichanE2E\Support\BrowserTester;
use Facebook\WebDriver\WebDriverKeys;

final class PaintCest
{
    public function _before(BrowserTester $I): void
    {
        $I->amOnPage('/mod/');
        $I->fillField('input[name="username"]', 'admin');
        $I->fillField('input[name="password"]', 'password');
        $I->click('input[name="login"]');
        $I->waitForElement('body.is-moderator');
        $I->setCookie('e2e_paint', '1');
        $I->amOnPage('/mod.php?/b/edit/1');
        $I->waitForJS('return !!window.paintTool;', 10);
        $I->executeJS('window.paintTool.open(); window.paintTool.state.opacity = 1; window.paintTool.state.color = "#000000";');
        $I->waitForElementVisible('.paint-canvas');
    }

    public function shapesAndFilledRectanglesHaveSeparateUndoSteps(BrowserTester $I): void
    {
        $states = [$this->pixels($I)];
        foreach (['rect', 'circle', 'line', 'brush', 'eraser', 'fill'] as $tool) {
            $I->click('[data-tool="' . $tool . '"]');
            if ($tool === 'rect') {
                $I->uncheckOption('.rect-fill-ctl');
            }
            $this->drag($I, 50, 50, 200, 150);
            $states[] = $this->pixels($I);
            $I->assertNotSame($states[count($states) - 2], end($states), $tool);
        }
        $I->click('[data-tool="rect"]');
        $I->checkOption('.rect-fill-ctl');
        $I->executeJS('window.paintTool.state.color = "#ff0000"; window.paintTool.state.opacity = 0.5;');
        $this->drag($I, 350, 250, 250, 180);
        $I->assertSame([128, 0, 0, 255], $I->executeJS('return Array.from(window.paintTool.engine.ctx.getImageData(300, 200, 1, 1).data);'));
        $states[] = $this->pixels($I);
        $I->makeScreenshot('paint-rectangle-fill');
        for ($index = count($states) - 2; $index >= 0; $index--) {
            $this->history($I, 'undo');
            $I->assertSame($states[$index], $this->pixels($I));
        }
        for ($index = 1; $index < count($states); $index++) {
            $this->history($I, 'redo');
            $I->assertSame($states[$index], $this->pixels($I));
        }
        $this->history($I, 'undo');
        $I->click('[data-tool="brush"]');
        $this->drag($I, 400, 200, 450, 250);
        $branched = $this->pixels($I);
        $this->history($I, 'redo');
        $I->assertSame($branched, $this->pixels($I));
    }

    public function textAndClearCanBeUndone(BrowserTester $I): void
    {
        $before = $this->pixels($I);
        $I->click('[data-tool="text"]');
        $I->click('.paint-canvas');
        $I->typeInPopup('Undo text');
        $I->acceptPopup();
        $text = $this->pixels($I);
        $I->assertNotSame($before, $text);
        $I->click('[data-action="clear"]');
        $I->assertSame($before, $this->pixels($I));
        $this->history($I, 'undo');
        $I->assertSame($text, $this->pixels($I));
        $I->pressKey('body', ['ctrl', 'z']);
        $I->waitForJS('return !window.paintTool.engine.historyLoad;', 10);
        $I->assertSame($before, $this->pixels($I));
        $I->pressKey('body', [WebDriverKeys::CONTROL, WebDriverKeys::SHIFT, 'z']);
        $I->waitForJS('return !window.paintTool.engine.historyLoad;', 10);
        $I->assertSame($text, $this->pixels($I));
    }

    public function fillWorksInsideShapesOnLightAndDarkBackgrounds(BrowserTester $I): void
    {
        foreach (['rect', 'circle'] as $shape) {
            foreach (['#ffffff', '#407fc0', '#222222'] as $background) {
                foreach (range(0, 5) as $gap) {
                    $I->executeJS(<<<JS
                        const eng = window.paintTool.engine;
                        eng.ctx.fillStyle = '$background'; eng.ctx.fillRect(0, 0, 600, 400);
                        eng.state.color = '#ffff80'; eng.state.brushSize = 4;
                        eng.state.rectFill = false;
                    JS);
                    $I->click('[data-tool="' . $shape . '"]');
                    $this->drag($I, 80, 80, 520, 320);
                    $before = $this->pixels($I);
                    $I->click('[data-tool="fill"]');
                    $I->executeJS(<<<JS
                        $('.paint-color-input').val('#ff0000').trigger('input');
                        $('.gapclose-ctl').val($gap).trigger('input');
                    JS);
                    $I->click('.paint-canvas');
                    $I->assertSame([255, 0, 0, 255], $I->executeJS('return Array.from(window.paintTool.engine.ctx.getImageData(300, 200, 1, 1).data);'), "$shape on $background, gap $gap");
                    $I->assertSame([255, 255, 128, 255], $I->executeJS('return Array.from(window.paintTool.engine.ctx.getImageData(300, 80, 1, 1).data);'));
                    $I->assertSame(sscanf(substr($background, 1), '%2x%2x%2x'), $I->executeJS('return Array.from(window.paintTool.engine.ctx.getImageData(20, 20, 1, 1).data).slice(0, 3);'));
                    $after = $this->pixels($I);
                    $this->history($I, 'undo');
                    $I->assertSame($before, $this->pixels($I));
                    $this->history($I, 'redo');
                    $I->assertSame($after, $this->pixels($I));
                }
            }
            $I->makeScreenshot('paint-fill-' . $shape);
        }
    }

    public function fillClosesGapsInLightOutlinesAndCanRecolorTheResult(BrowserTester $I): void
    {
        $I->executeJS(<<<'JS'
            const eng = window.paintTool.engine;
            eng.ctx.fillStyle = '#203060'; eng.ctx.fillRect(0, 0, 600, 400);
            eng.state.color = '#ffff80'; eng.state.rectFill = false; eng.state.brushSize = 4;
        JS);
        $I->click('[data-tool="rect"]');
        $this->drag($I, 80, 80, 520, 320);
        $I->executeJS(<<<'JS'
            const eng = window.paintTool.engine;
            eng.ctx.fillStyle = '#203060'; eng.ctx.fillRect(299, 76, 2, 8); eng.commitState();
            eng.state.color = '#ff0000';
        JS);
        $I->click('[data-tool="fill"]');
        $I->executeJS('$(".gapclose-ctl").val(0).trigger("input");');
        $I->click('.paint-canvas');
        $I->assertSame([255, 0, 0, 255], $I->executeJS('return Array.from(window.paintTool.engine.ctx.getImageData(20, 20, 1, 1).data);'));
        $this->history($I, 'undo');
        foreach (range(1, 5) as $gap) {
            $I->executeJS("$('.gapclose-ctl').val($gap).trigger('input'); $('.paint-color-input').val('#ff0000').trigger('input');");
            $I->click('.paint-canvas');
            $I->assertSame([255, 0, 0, 255], $I->executeJS('return Array.from(window.paintTool.engine.ctx.getImageData(300, 200, 1, 1).data);'));
            $I->assertSame([32, 48, 96, 255], $I->executeJS('return Array.from(window.paintTool.engine.ctx.getImageData(20, 20, 1, 1).data);'));
            $I->executeJS('$(".paint-color-input").val("#0080ff").trigger("input");');
            $I->click('.paint-canvas');
            $I->assertSame([0, 128, 255, 255], $I->executeJS('return Array.from(window.paintTool.engine.ctx.getImageData(300, 200, 1, 1).data);'));
            $I->assertSame([32, 48, 96, 255], $I->executeJS('return Array.from(window.paintTool.engine.ctx.getImageData(20, 20, 1, 1).data);'));
            $this->history($I, 'undo');
            $this->history($I, 'undo');
        }
    }

    public function fillRecolorsARedRectangleWithGapClose(BrowserTester $I): void
    {
        $I->click('[data-tool="rect"]');
        $I->checkOption('.rect-fill-ctl');
        $I->executeJS('window.paintTool.state.color = "#ff0000";');
        $this->drag($I, 80, 80, 520, 320);
        $before = $this->pixels($I);
        $I->click('[data-tool="fill"]');
        $I->executeJS('$(".paint-color-input").val("#000000").trigger("input");');
        $filled = null;
        foreach (range(0, 5) as $gap) {
            $I->executeJS("$('.gapclose-ctl').val($gap).trigger('input');");
            $I->click('.paint-canvas');
            $I->assertSame([0, 0, 0, 255], $I->executeJS('return Array.from(window.paintTool.engine.ctx.getImageData(300, 200, 1, 1).data);'));
            $I->assertSame([255, 255, 255, 255], $I->executeJS('return Array.from(window.paintTool.engine.ctx.getImageData(20, 20, 1, 1).data);'));
            $after = $this->pixels($I);
            $filled ??= $after;
            $I->assertSame($filled, $after, "Gap $gap must not leave an unfilled border");
            $this->history($I, 'undo');
            $I->assertSame($before, $this->pixels($I));
            $this->history($I, 'redo');
            $I->assertSame($after, $this->pixels($I));
            $I->makeScreenshot('paint-fill-red-to-black-gap-' . $gap);
            $this->history($I, 'undo');
        }
    }

    public function pixelateOnlyChangesTheRectangleAndScalesWithTheImage(BrowserTester $I): void
    {
        foreach ([600, 4000] as $width) {
            $I->executeJS(<<<JS
                const eng = window.paintTool.engine;
                eng.resize($width, $width / 2);
                const gradient = eng.ctx.createLinearGradient(0, 0, $width, 0);
                gradient.addColorStop(0, '#203060'); gradient.addColorStop(1, '#f8c080');
                eng.ctx.fillStyle = gradient; eng.ctx.fillRect(0, 0, $width, $width / 2);
                eng.ctx.fillStyle = '#ffffff'; eng.ctx.font = ($width / 12) + 'px sans-serif';
                eng.ctx.fillText('Pixelate sample', $width / 4, $width / 4);
                eng.commitState();
            JS);
            $before = $this->pixels($I);
            $I->click('[data-tool="pixelate"]');
            $I->assertSame((int) round($width / 80), $I->executeJS('return Number(document.querySelector(".pixel-size-ctl").value);'));
            $bounds = json_encode($this->drag($I, $width * .8, $width * .4, $width * .2, $width * .1));
            $after = $this->pixels($I);
            $I->assertNotSame($before, $after);
            $I->assertTrue($I->executeJS(<<<JS
                const eng = window.paintTool.engine, before = eng.snapshot;
                const after = eng.ctx.getImageData(0, 0, eng.canvas.width, eng.canvas.height);
                const [left, top, right, bottom] = $bounds;
                let changed = false;
                for (let y = 0; y < after.height; y++) {
                    for (let x = 0; x < after.width; x++) {
                        const i = (y * after.width + x) * 4;
                        const same = after.data[i] === before.data[i] && after.data[i+1] === before.data[i+1] && after.data[i+2] === before.data[i+2];
                        const inside = x >= left && x < right && y >= top && y < bottom;
                        if (!inside && !same) return false;
                        if (inside && !same) changed = true;
                    }
                }
                const size = eng.canvas.width / Math.ceil(eng.canvas.width / eng.pixelSize);
                const x = Math.ceil($width * .3 / size) * size + 1;
                const y = $width * .2;
                const block = eng.ctx.getImageData(x, y, Math.floor(size) - 2, 1).data;
                for (let i = 4; i < block.length; i += 4) {
                    if (block[i] !== block[0] || block[i+1] !== block[1] || block[i+2] !== block[2]) return false;
                }
                return changed;
            JS));
            $I->makeScreenshot('paint-pixelate-' . $width);
            $this->history($I, 'undo');
            $I->assertSame($before, $this->pixels($I));
            $this->history($I, 'redo');
            $I->assertSame($after, $this->pixels($I));
        }
    }

    public function resizeLoadAndRapidUndoRestorePixelsAndDimensions(BrowserTester $I): void
    {
        $I->click('[data-tool="rect"]');
        $I->checkOption('.rect-fill-ctl');
        $this->drag($I, 50, 50, 180, 150);
        $before = $this->pixels($I);
        $I->fillField('#paint-w', '4000');
        $I->fillField('#paint-h', '2400');
        $I->click('[data-action="resize"]');
        $large = $this->pixels($I);
        $this->assertRulers($I, 4000, 2400);
        $I->makeScreenshot('paint-large-rulers');
        $this->history($I, 'undo');
        $I->assertSame($before, $this->pixels($I));
        $I->seeInField('#paint-w', '600');
        $I->seeInField('#paint-h', '400');
        $this->history($I, 'redo');
        $I->assertSame($large, $this->pixels($I));
        $I->executeJS('window.paintTool.loadImage("/static/banners/default.png");');
        $I->waitForJS('return window.paintTool.engine.canvas.width === 300;', 10);
        $loaded = $this->pixels($I);
        $this->history($I, 'undo');
        $I->assertSame($large, $this->pixels($I));
        $this->history($I, 'redo');
        $I->assertSame($loaded, $this->pixels($I));
        $I->executeJS('const eng = window.paintTool.engine; eng.undo(); eng.undo(); eng.redo();');
        $I->waitForJS('return !window.paintTool.engine.historyLoad;', 10);
        $I->assertSame($large, $this->pixels($I));
        $this->assertRulers($I, 4000, 2400);
    }

    public function selectionTransformsCanBeUndoneBeforeApply(BrowserTester $I): void
    {
        $I->click('[data-tool="rect"]');
        $I->checkOption('.rect-fill-ctl');
        $this->drag($I, 80, 70, 160, 120);
        $original = $this->pixels($I);
        $I->click('[data-tool="selection"]');
        $this->drag($I, 50, 50, 220, 180);
        $this->drag($I, 130, 110, 270, 160);
        $moved = $I->executeJS('return { ...window.paintTool.state.selection.transform };');
        $I->click('[data-action="flipH"]');
        $this->history($I, 'undo');
        $I->assertSame($moved, $I->executeJS('return { ...window.paintTool.state.selection.transform };'));
        $this->history($I, 'undo');
        $I->assertSame('pristine', $I->executeJS('return window.paintTool.state.selection.phase;'));
        $this->history($I, 'redo');
        $I->assertSame($moved, $I->executeJS('return { ...window.paintTool.state.selection.transform };'));
        $this->history($I, 'redo');
        $flipped = $I->executeJS('return { ...window.paintTool.state.selection.transform };');
        $this->history($I, 'redo');
        $I->assertSame($flipped, $I->executeJS('return { ...window.paintTool.state.selection.transform };'));
        $this->history($I, 'undo');
        $I->click('[data-action="duplicate"]');
        $this->history($I, 'undo');
        $I->assertSame($moved, $I->executeJS('return { ...window.paintTool.state.selection.transform };'));
        $I->click('.paint-tool-options [data-action="apply"]');
        $changed = $this->pixels($I);
        $I->assertNotSame($original, $changed);
        $this->history($I, 'undo');
        $I->assertSame($original, $this->pixels($I));
        $this->history($I, 'redo');
        $I->assertSame($changed, $this->pixels($I));
    }

    public function pastingFinishesTheSelectionAndKeepsUndoSteps(BrowserTester $I): void
    {
        $I->click('[data-tool="rect"]');
        $I->checkOption('.rect-fill-ctl');
        $this->drag($I, 80, 70, 160, 120);
        $original = $this->pixels($I);
        foreach ([false, true] as $move) {
            $I->click('[data-tool="selection"]');
            $this->drag($I, 40, 40, 220, 180);
            if ($move) {
                $this->drag($I, 130, 110, 230, 150);
            }
            $I->executeJS(<<<'JS'
                const source = document.createElement('canvas'); source.width = source.height = 40;
                const ctx = source.getContext('2d'); ctx.fillStyle = '#ff0000'; ctx.fillRect(0, 0, 40, 40);
                source.toBlob(blob => {
                    const data = new DataTransfer();
                    data.items.add(new File([blob], 'paste.png', {type: 'image/png'}));
                    document.dispatchEvent(new ClipboardEvent('paste', {clipboardData: data, bubbles: true}));
                });
            JS);
            $I->waitForJS('return !window.paintTool.state.selection.active;', 10);
            $I->executeJS('window.paintTool.engine.renderSelection();');
            $I->assertSame([255, 0, 0, 255], $I->executeJS('return Array.from(window.paintTool.engine.ctx.getImageData(300, 200, 1, 1).data);'));
            $I->assertTrue($I->executeJS('const eng = window.paintTool.engine; return eng.canvas.toDataURL() === eng.history[eng.historyIndex];'));
            $pasted = $this->pixels($I);
            $I->makeScreenshot('paint-paste-selection-' . ($move ? 'moved' : 'pristine'));
            $this->history($I, 'undo');
            if ($move) {
                $I->assertSame([0, 0, 0, 255], $I->executeJS('return Array.from(window.paintTool.engine.ctx.getImageData(210, 140, 1, 1).data);'));
                $I->assertSame([255, 255, 255, 255], $I->executeJS('return Array.from(window.paintTool.engine.ctx.getImageData(100, 90, 1, 1).data);'));
                $this->history($I, 'undo');
            }
            $I->assertSame($original, $this->pixels($I));
            $this->history($I, 'redo');
            if ($move) $this->history($I, 'redo');
            $I->assertSame($pasted, $this->pixels($I));
            $this->history($I, 'undo');
            if ($move) $this->history($I, 'undo');
        }
    }

    public function failedLoadKeepsTheDrawingUsable(BrowserTester $I): void
    {
        $I->click('[data-tool="brush"]');
        $this->drag($I, 40, 40, 200, 150);
        $before = $this->pixels($I);
        $I->executeJS('window.paintTool.loadImage("/tests/Support/Data/invalid-image.png");');
        $I->waitForText('Could not load image.', 10, '#alert_message');
        $I->makeScreenshot('paint-load-error');
        $I->click('#alert_div .alert_button');
        $I->waitForElementNotVisible('#alert_handler');
        $I->assertFalse($I->executeJS('return document.querySelector(".paint-actions [data-action=done]").disabled;'));
        $I->assertSame($before, $this->pixels($I));
        $I->executeJS('window.paintTool.onExport = blob => { window.paintExportSize = blob.size; };');
        $I->click('.paint-actions [data-action="done"]');
        $I->waitForElementNotVisible('.paint-modal-overlay');
        $I->assertGreaterThan(0, $I->executeJS('return window.paintExportSize;'));

        $I->executeJS('window.paintTool.open("/tests/Support/Data/invalid-image.png");');
        $I->waitForText('Could not load image.', 10, '#alert_message');
        $I->click('#alert_div .alert_button');
        $I->waitForElementNotVisible('#alert_handler');
        $I->assertTrue($I->executeJS('return document.querySelector(".paint-actions [data-action=done]").disabled;'));
        $I->executeJS('window.paintTool.loadImage("/static/banners/default.png");');
        $I->waitForJS('return window.paintTool.engine.canvas.width === 300;', 10);
        $I->assertFalse($I->executeJS('return document.querySelector(".paint-actions [data-action=done]").disabled;'));
    }

    public function undoAndRedoKeepThePixelBlockSize(BrowserTester $I): void
    {
        $I->click('[data-tool="pixelate"]');
        $I->executeJS(<<<'JS'
            const eng = window.paintTool.engine, ctx = eng.ctx;
            const gradient = ctx.createLinearGradient(0, 0, 600, 0);
            gradient.addColorStop(0, '#000000'); gradient.addColorStop(1, '#ffffff');
            ctx.fillStyle = gradient; ctx.fillRect(0, 0, 600, 400); eng.commitState();
            $('.pixel-size-ctl').val(23).trigger('input');
        JS);
        $this->drag($I, 50, 50, 500, 300);
        $this->history($I, 'undo');
        $I->assertSame([23, 23], $I->executeJS('return [window.paintTool.engine.pixelSize, Number(document.querySelector(".pixel-size-ctl").value)];'));
        $this->history($I, 'redo');
        $I->assertSame([23, 23], $I->executeJS('return [window.paintTool.engine.pixelSize, Number(document.querySelector(".pixel-size-ctl").value)];'));
        $I->fillField('#paint-w', '80');
        $I->fillField('#paint-h', '60');
        $I->click('[data-action="resize"]');
        $this->history($I, 'undo');
        $I->executeJS('$(".pixel-size-ctl").val(23).trigger("input");');
        $this->history($I, 'redo');
        $I->assertSame([23, 23], $I->executeJS('return [window.paintTool.engine.pixelSize, Number(document.querySelector(".pixel-size-ctl").value)];'));
    }

    private function assertRulers(BrowserTester $I, int $w, int $h): void
    {
        $I->assertSame([$w, $h], $I->executeJS('const c = document.querySelector(".paint-canvas"); return [c.width, c.height];'));
        $I->assertSame([20, 20, 0, 0, true], $I->executeJS(<<<'JS'
            const canvas = document.querySelector('.paint-canvas').getBoundingClientRect();
            const top = document.querySelector('.paint-ruler-top').getBoundingClientRect();
            const left = document.querySelector('.paint-ruler-left').getBoundingClientRect();
            return [Math.round(top.height), Math.round(left.width),
                Math.round(top.width - canvas.width), Math.round(left.height - canvas.height),
                canvas.right <= innerWidth && canvas.bottom <= innerHeight];
        JS));
    }

    private function pixels(BrowserTester $I): string
    {
        return hash('sha256', $I->executeJS('return window.paintTool.engine.canvas.toDataURL();'));
    }

    private function history(BrowserTester $I, string $action): void
    {
        $I->click('.paint-toolbar [data-action="' . $action . '"]');
        $I->waitForJS('return !window.paintTool.engine.historyLoad;', 10);
    }

    private function drag(BrowserTester $I, float $x1, float $y1, float $x2, float $y2): array
    {
        return $I->executeJS(<<<JS
            const canvas = document.querySelector('.paint-canvas'), r = canvas.getBoundingClientRect();
            const event = (type, x, y) => {
                const e = new MouseEvent(type, {
                    clientX: r.left + x * r.width / canvas.width,
                    clientY: r.top + y * r.height / canvas.height, bubbles: true, buttons: type === 'mouseup' ? 0 : 1
                });
                canvas.dispatchEvent(e);
                return [Math.round((e.clientX - r.left) * canvas.width / r.width),
                    Math.round((e.clientY - r.top) * canvas.height / r.height)];
            };
            const a = event('mousedown', $x1, $y1), b = event('mousemove', $x2, $y2);
            event('mouseup', $x2, $y2);
            return [Math.min(a[0], b[0]), Math.min(a[1], b[1]), Math.max(a[0], b[0]), Math.max(a[1], b[1])];
        JS);
    }
}
