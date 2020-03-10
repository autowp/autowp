<?php

namespace Application\Telegram\Command;

use Telegram\Bot\Actions;
use Telegram\Bot\Commands\Command;

use function sprintf;

use const PHP_EOL;

class StartCommand extends Command
{
    protected string $name = "start";

    protected string $description = "Start Command to get you started";

    /**
     * @inheritDoc
     */
    public function handle($arguments)
    {
        $this->replyWithMessage([
            'text' => 'Hello! Welcome to our bot, Here are our available commands:',
        ]);

        $this->replyWithChatAction([
            'action' => Actions::TYPING,
        ]);

        $commands = $this->getTelegram()->getCommands();

        // Build the list
        $response = '';
        foreach ($commands as $name => $command) {
            $response .= sprintf('/%s - %s' . PHP_EOL, $name, $command->getDescription());
        }

        // Reply with the commands list
        $this->replyWithMessage([
            'disable_web_page_preview' => true,
            'text'                     => $response,
        ]);

        // Trigger another command dynamically from within this command
        // When you want to chain multiple commands within one or process the request further.
        // The method supports second parameter arguments which you can optionally pass, By default
        // it'll pass the same arguments that are received for this command originally.
        //$this->triggerCommand('subscribe');
    }
}
