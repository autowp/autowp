<?php

namespace Application\Telegram\Command;

use Application\Model\Item;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Telegram\Bot\Commands\Command;

use function array_replace;
use function Autowp\Commons\currentFromResultSetInterface;

class InboxCommand extends Command
{
    /** @var string */
    protected $name = "inbox";

    /** @var string */
    protected $description = "Subscribe to inbox pictures";

    private TableGateway $telegramItemTable;

    private TableGateway $telegramChatTable;

    private TableGateway $itemTable;

    public function __construct(
        TableGateway $telegramItemTable,
        TableGateway $telegramChatTable,
        TableGateway $itemTable
    ) {
        $this->telegramItemTable = $telegramItemTable;
        $this->telegramChatTable = $telegramChatTable;
        $this->itemTable         = $itemTable;
    }

    /**
     * @suppress PhanUndeclaredMethod, PhanPluginMixedKeyNoKey
     * @inheritDoc
     * @param mixed $arguments
     * @throws Exception
     */
    public function handle($arguments)
    {
        $chatId = (int) $this->getUpdate()->getMessage()->getChat()->getId();

        $select = new Sql\Select($this->telegramChatTable->getTable());
        $select->join('users', 'telegram_chat.user_id = users.id', [])
            ->where([
                'chat_id' => $chatId,
                'not users.deleted',
            ])
            ->limit(1);

        $chatRow = currentFromResultSetInterface($this->telegramChatTable->selectWith($select));

        if (! $chatRow) {
            $this->replyWithMessage([
                'text' => 'You need to identify your account with /me command to use that service',
            ]);
            return;
        }

        if ($arguments) {
            $brandRow = currentFromResultSetInterface($this->itemTable->select([
                'name'         => (string) $arguments,
                'item_type_id' => Item::BRAND,
            ]));

            if ($brandRow) {
                $primaryKey       = [
                    'item_id' => $brandRow['id'],
                    'chat_id' => $chatId,
                ];
                $telegramBrandRow = currentFromResultSetInterface($this->telegramItemTable->select($primaryKey));

                if ($telegramBrandRow && $telegramBrandRow['inbox']) {
                    $this->telegramItemTable->update(['inbox' => 0], $primaryKey);
                    $this->replyWithMessage([
                        'text' => 'Successful unsubscribed from ' . $brandRow['name'],
                    ]);
                } else {
                    $set = ['inbox' => 1];
                    if ($telegramBrandRow) {
                        $this->telegramItemTable->update($set, $primaryKey);
                    } else {
                        $this->telegramItemTable->insert(array_replace($set, $primaryKey));
                    }

                    $this->replyWithMessage([
                        'text' => 'Successful subscribed to ' . $brandRow['name'],
                    ]);
                }
            } else {
                $this->replyWithMessage([
                    'text' => 'Brand "' . $arguments . '" not found',
                ]);
            }
        } else {
            $this->replyWithMessage([
                'text' => "Plase, type brand name. For Example /inbox BMW",
            ]);
        }
    }
}
