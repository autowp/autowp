<?php

namespace ApplicationTest\Command;

use Application\Command\SpecsRefreshConflictFlagsCommandFactory;
use Application\Command\SpecsRefreshItemConflictFlagsCommandFactory;
use Application\Command\SpecsRefreshUsersStatCommandFactory;
use Application\Command\SpecsRefreshUserStatCommandFactory;
use Application\Test\AbstractConsoleControllerTestCase;
use Exception;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

class SpecsCommandTest extends AbstractConsoleControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    /**
     * @throws Exception
     */
    public function testRefreshConflictFlags(): void
    {
        $factory = new SpecsRefreshConflictFlagsCommandFactory();
        $command = $factory($this->getApplicationServiceLocator(), '');

        $result = $command->run(
            new ArgvInput(['app:specs-refresh-conflict-flags']),
            new BufferedOutput()
        );

        $this->assertEquals(0, $result);
    }

    /**
     * @throws Exception
     */
    public function testRefreshItemConflictFlags(): void
    {
        $factory = new SpecsRefreshItemConflictFlagsCommandFactory();
        $command = $factory($this->getApplicationServiceLocator(), '');

        $result = $command->run(
            new ArgvInput(['app:specs-refresh-item-conflict-flags', '1']),
            new BufferedOutput()
        );

        $this->assertEquals(0, $result);
    }

    /**
     * @throws Exception
     */
    public function testRefreshUserStat(): void
    {
        $factory = new SpecsRefreshUserStatCommandFactory();
        $command = $factory($this->getApplicationServiceLocator(), '');

        $result = $command->run(
            new ArgvInput(['app:specs-refresh-user-stat', '1']),
            new BufferedOutput()
        );

        $this->assertEquals(0, $result);
    }

    /**
     * @throws Exception
     */
    public function testRefreshUsersStat(): void
    {
        $factory = new SpecsRefreshUsersStatCommandFactory();
        $command = $factory($this->getApplicationServiceLocator(), '');

        $result = $command->run(
            new ArgvInput(['app:specs-refresh-users-stat']),
            new BufferedOutput()
        );

        $this->assertEquals(0, $result);
    }
}
