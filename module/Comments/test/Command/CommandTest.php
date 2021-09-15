<?php

namespace AutowpTest\Comments\Command;

use Application\Test\AbstractConsoleControllerTestCase;
use Autowp\Comments\Command\CleanupDeletedCommandFactory;
use Autowp\Comments\Command\RefreshRepliesCountCommandFactory;
use Exception;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

class CommandTest extends AbstractConsoleControllerTestCase
{
    protected string $applicationConfigPath = __DIR__ . '/../../../../config/application.config.php';

    /**
     * @throws Exception
     */
    public function testRefreshRepliesCount(): void
    {
        $factory = new RefreshRepliesCountCommandFactory();
        $command = $factory($this->getApplicationServiceLocator(), '');

        $result = $command->run(
            new ArgvInput(['comments:refresh-replies-count']),
            new BufferedOutput()
        );

        $this->assertEquals(0, $result);
    }

    /**
     * @throws Exception
     */
    public function testCleanupDeleted(): void
    {
        $factory = new CleanupDeletedCommandFactory();
        $command = $factory($this->getApplicationServiceLocator(), '');

        $result = $command->run(
            new ArgvInput(['comments:cleanup-deleted']),
            new BufferedOutput()
        );

        $this->assertEquals(0, $result);
    }
}
