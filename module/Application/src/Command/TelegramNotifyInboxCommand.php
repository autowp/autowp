<?php

namespace Application\Command;

use Application\Service\TelegramService;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TelegramNotifyInboxCommand extends Command
{
    private TelegramService $telegram;

    /** @var string|null The default command name */
    protected static $defaultName = 'telegram-notify-inbox';

    protected function configure(): void
    {
        $this->setName(self::$defaultName);
        $this->addArgument('picture_id', InputArgument::REQUIRED);
    }

    public function __construct(string $name, TelegramService $telegram)
    {
        parent::__construct($name);

        $this->telegram = $telegram;
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->telegram->notifyInbox($input->getArgument('picture_id'));

        return 0;
    }
}
