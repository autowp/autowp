<?php

namespace Application\Command;

use Application\Service\SpecificationsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SpecsRefreshUserStatCommand extends Command
{
    private SpecificationsService $specsService;

    /** @var string|null The default command name */
    protected static $defaultName = 'specs-refresh-user-stat';

    protected function configure(): void
    {
        $this->setName(self::$defaultName);
        $this->addArgument('user_id', InputArgument::REQUIRED);
    }

    public function __construct(string $name, SpecificationsService $specsService)
    {
        parent::__construct($name);

        $this->specsService = $specsService;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = $input->getArgument('user_id');

        $this->specsService->refreshUserConflicts($userId);

        return 0;
    }
}
