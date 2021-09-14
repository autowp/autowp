<?php

namespace Autowp\Comments\Command;

use Autowp\Comments\CommentsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupDeletedCommand extends Command
{
    private CommentsService $service;

    /** @var string|null The default command name */
    protected static $defaultName = 'cleanup-deleted';

    protected function configure(): void
    {
        $this->setName(self::$defaultName);
    }

    public function __construct(string $name, CommentsService $message)
    {
        parent::__construct($name);

        $this->service = $message;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $affected = $this->service->cleanupDeleted();

        $output->writeln("ok. Deleted: $affected");

        return 0;
    }
}
