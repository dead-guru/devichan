<?php

declare(strict_types=1);

namespace DevichanE2E\Support;

/**
 * @method void wantTo($text)
 * @method void wantToTest($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argument)
 */
final class InstallerTester extends \Codeception\Actor
{
    use _generated\InstallerTesterActions;
}
