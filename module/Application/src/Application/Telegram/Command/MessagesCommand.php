<?php

namespace Application\Telegram\Command;

use Telegram\Bot\Commands\Command;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

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
     * @var TableGateway
     */
    private $telegramChatTable;

    public function __construct(TableGateway $telegramChatTable)
    {
        $this->telegramChatTable = $telegramChatTable;
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @inheritdoc
     */
    public function handle($arguments)
    {
        $chatId = (int)$this->getUpdate()->getMessage()->getChat()->getId();

        $primaryKey = [
            'chat_id' => $chatId
        ];

        $select = new Sql\Select($this->telegramChatTable->getTable());
        $select->join('users', 'telegram_chat.user_id = users.id', [])
            ->where(array_replace([
                'not users.deleted'
            ], $primaryKey));
        $chatRow = $this->telegramChatTable->selectWith($select)->current();

        if (! $chatRow) {
            $this->replyWithMessage([
                'text' => 'You need to identify your account with /me command to use that service'
            ]);
            return;
        }

        $value = $arguments == 'on' ? 1 : 0;

        $this->telegramChatTable->update([
            'messages' => $value
        ], $primaryKey);

        if ($value) {
            $this->replyWithMessage([
                'text' => "Subscription to new personal messages is enabled. Send `/messages off` to disable"
            ]);
            return;
        }

        $this->replyWithMessage([
            'text' => "Subscription to new personal messages is disabled. Send `/messages on` to enable"
        ]);
    }
}
