<?php

namespace Application\Telegram\Command;

use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Telegram\Bot\Commands\Command;

use function array_replace;

class MessagesCommand extends Command
{
    protected string $name = "messages";

    protected string $description = "Enable/disable personal messages";

    private TableGateway $telegramChatTable;

    public function __construct(TableGateway $telegramChatTable)
    {
        $this->telegramChatTable = $telegramChatTable;
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @inheritDoc
     * @param mixed $arguments
     */
    public function handle($arguments)
    {
        $chatId = (int) $this->getUpdate()->getMessage()->getChat()->getId();

        $primaryKey = [
            'chat_id' => $chatId,
        ];

        $select = new Sql\Select($this->telegramChatTable->getTable());
        $select->join('users', 'telegram_chat.user_id = users.id', [])
            ->where(array_replace([
                'not users.deleted',
            ], $primaryKey));
        $chatRow = $this->telegramChatTable->selectWith($select)->current();

        if (! $chatRow) {
            $this->replyWithMessage([
                'text' => 'You need to identify your account with /me command to use that service',
            ]);
            return;
        }

        $value = $arguments === 'on' ? 1 : 0;

        $this->telegramChatTable->update([
            'messages' => $value,
        ], $primaryKey);

        if ($value) {
            $this->replyWithMessage([
                'text' => "Subscription to new personal messages is enabled. Send `/messages off` to disable",
            ]);
            return;
        }

        $this->replyWithMessage([
            'text' => "Subscription to new personal messages is disabled. Send `/messages on` to enable",
        ]);
    }
}
