<?php

namespace Application\Command;

use Application\Service\SpecificationsService;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SpecsRefreshItemConflictFlagsCommand extends Command
{
    private SpecificationsService $specsService;

    /** @var string|null The default command name */
    protected static $defaultName = 'specs-refresh-item-conflict-flags';

    protected function configure(): void
    {
        $this->setName(self::$defaultName);
        $this->addArgument('item_id', InputArgument::REQUIRED);
    }

    public function __construct(string $name, SpecificationsService $specsService)
    {
        parent::__construct($name);

        $this->specsService = $specsService;
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $itemId = $input->getArgument('item_id');

        $this->specsService->refreshItemConflictFlags($itemId);

        return 0;
    }
}
