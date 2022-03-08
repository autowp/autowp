<?php

namespace Autowp\Comments\Command;

use Autowp\Comments\CommentsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RefreshRepliesCountCommand extends Command
{
    private CommentsService $service;

    /** @var string|null The default command name */
    protected static $defaultName = 'refresh-replies-count';

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
        $affected = $this->service->updateRepliesCount();

        $output->writeln("ok. Affected: $affected");

        return 0;
    }
}
