<?php

namespace Application\Telegram\Command;

use Telegram\Bot\Commands\Command;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Application\Model\Item;

class InboxCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "inbox";

    /**
     * @var string Command Description
     */
    protected $description = "Subscribe to inbox pictures";

    /**
     * @var TableGateway
     */
    private $telegramItemTable;

    /**
     * @var TableGateway
     */
    private $telegramChatTable;

    /**
     * @var TableGateway
     */
    private $itemTable;

    public function __construct(
        TableGateway $telegramItemTable,
        TableGateway $telegramChatTable,
        TableGateway $itemTable
    ) {
        $this->telegramItemTable = $telegramItemTable;
        $this->telegramChatTable = $telegramChatTable;
        $this->itemTable = $itemTable;
    }

    /**
     * @suppress PhanUndeclaredMethod, PhanPluginMixedKeyNoKey
     * @inheritdoc
     */
    public function handle($arguments)
    {
        $chatId = (int)$this->getUpdate()->getMessage()->getChat()->getId();

        $select = new Sql\Select($this->telegramChatTable->getTable());
        $select->join('users', 'telegram_chat.user_id = users.id', [])
            ->where([
                'chat_id' => $chatId,
                'not users.deleted'
            ])
            ->limit(1);

        $chatRow = $this->telegramChatTable->selectWith($select)->current();

        if (! $chatRow) {
            $this->replyWithMessage([
                'text' => 'You need to identify your account with /me command to use that service'
            ]);
            return;
        }

        if ($arguments) {
            $brandRow = $this->itemTable->select([
                'name'         => (string)$arguments,
                'item_type_id' => Item::BRAND
            ])->current();

            if ($brandRow) {
                $primaryKey = [
                    'item_id' => $brandRow['id'],
                    'chat_id' => $chatId
                ];
                $telegramBrandRow = $this->telegramItemTable->select($primaryKey)->current();

                if ($telegramBrandRow && $telegramBrandRow['inbox']) {
                    $this->telegramItemTable->update(['inbox' => 0], $primaryKey);
                    $this->replyWithMessage([
                        'text' => 'Successful unsubscribed from ' . $brandRow['name']
                    ]);
                } else {
                    $set = ['inbox' => 1];
                    if ($telegramBrandRow) {
                        $this->telegramItemTable->update($set, $primaryKey);
                    } else {
                        $this->telegramItemTable->insert(array_replace($set, $primaryKey));
                    }

                    $this->replyWithMessage([
                        'text' => 'Successful subscribed to ' . $brandRow['name']
                    ]);
                }
            } else {
                $this->replyWithMessage([
                    'text' => 'Brand "' . $arguments . '" not found'
                ]);
            }
        } else {
            $this->replyWithMessage([
                'text' => "Plase, type brand name. For Example /inbox BMW"
            ]);
        }
    }
}
