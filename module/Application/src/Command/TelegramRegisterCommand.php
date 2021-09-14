<?php

namespace Application\Command;

use Application\Service\TelegramService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TelegramRegisterCommand extends Command
{
    private TelegramService $telegram;

    /** @var string|null The default command name */
    protected static $defaultName = 'telegram-register';

    protected function configure(): void
    {
        $this->setName(self::$defaultName);
    }

    public function __construct(string $name, TelegramService $telegram)
    {
        parent::__construct($name);

        $this->telegram = $telegram;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->telegram->registerWebhook();

        return 0;
    }
}
