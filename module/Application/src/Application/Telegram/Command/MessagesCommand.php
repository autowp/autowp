<?php

namespace Application\Telegram\Command;

use Telegram\Bot\Commands\Command;

use Application\Model\DbTable;

class MessagesCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "messages";

    /**
     * @var string Command Description
     */
    protected $description = "Enable/disable personal messages";

    /**
     * @inheritdoc
     */
    public function handle($arguments)
    {
        $chatId = (int)$this->getUpdate()->getMessage()->getChat()->getId();

        $chatTable = new DbTable\Telegram\Chat();

        $chatRow = $chatTable->fetchRow(
            $chatTable->select(true)
                ->where('chat_id = ?', $chatId)
                ->join('users', 'telegram_chat.user_id = users.id', null)
                ->where('not users.deleted')
        );

        if (! $chatRow) {
            $this->replyWithMessage([
                'text' => 'You need to identify your account with /me command to use that service'
            ]);
            return;
        }

        if ($arguments == 'on') {
            $chatRow->messages = 1;
            $chatRow->save();
        } elseif ($arguments == 'off') {
            $chatRow->messages = 0;
            $chatRow->save();
        }

        if ($chatRow->messages) {
            $this->replyWithMessage([
                'text' => "Subscription to new personal messages is enabled. Send `/messages off` to disable"
            ]);
        } else {
            $this->replyWithMessage([
                'text' => "Subscription to new personal messages is disabled. Send `/messages on` to enable"
            ]);
        }
    }
}
