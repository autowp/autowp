<?php

namespace Application\Command;

use Application\Service\SpecificationsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SpecsRefreshActualValuesCommand extends Command
{
    private SpecificationsService $specsService;

    /** @var string|null The default command name */
    protected static $defaultName = 'specs-refresh-actual-values';

    protected function configure(): void
    {
        $this->setName(self::$defaultName);
    }

    public function __construct(string $name, SpecificationsService $specsService)
    {
        parent::__construct($name);

        $this->specsService = $specsService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->specsService->updateAllActualValues();

        return 0;
    }
}
