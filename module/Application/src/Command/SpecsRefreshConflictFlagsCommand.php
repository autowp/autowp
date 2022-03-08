<?php

namespace Application\Command;

use Application\Service\SpecificationsService;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SpecsRefreshConflictFlagsCommand extends Command
{
    private SpecificationsService $specsService;

    /** @var string|null The default command name */
    protected static $defaultName = 'specs-refresh-conflict-flags';

    protected function configure(): void
    {
        $this->setName(self::$defaultName);
    }

    public function __construct(string $name, SpecificationsService $specsService)
    {
        parent::__construct($name);

        $this->specsService = $specsService;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->specsService->refreshConflictFlags();

        return 0;
    }
}
