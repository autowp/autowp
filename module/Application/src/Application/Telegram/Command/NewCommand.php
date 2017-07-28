<?php

namespace Application\Telegram\Command;

use Telegram\Bot\Commands\Command;
use Zend\Db\TableGateway\TableGateway;

use Application\Model\DbTable;
use Application\Model\Item;

class NewCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "new";

    /**
     * @var string Command Description
     */
    protected $description = "Subscribe to new pictures";

    /**
     * @var TableGateway
     */
    private $telegramItemTable;

    /**
     * @var TableGateway
     */
    private $telegramChatTable;

    public function __construct(TableGateway $telegramItemTable, TableGateway $telegramChatTable)
    {
        $this->telegramItemTable = $telegramItemTable;
        $this->telegramChatTable = $telegramChatTable;
    }

    /**
     * @inheritdoc
     */
    public function handle($arguments)
    {
        if ($arguments) {
            $itemTable = new DbTable\Item();

            $brandRow = $itemTable->fetchRow([
                'name = ?'         => (string)$arguments,
                'item_type_id = ?' => Item::BRAND
            ]);

            if ($brandRow) {
                $chatId = (int)$this->getUpdate()->getMessage()->getChat()->getId();

                $primaryKey = [
                    'item_id' => $brandRow->id,
                    'chat_id' => $chatId,
                ];

                $telegramBrandRow = $this->telegramItemTable->select($primaryKey)->current();

                if ($telegramBrandRow && $telegramBrandRow['new']) {
                    $this->telegramItemTable->update(['new' => 0], $primaryKey);
                    $this->replyWithMessage([
                        'text' => 'Successful unsubscribed from ' . $brandRow->name
                    ]);
                } else {
                    $set = ['new' => 1];
                    if ($telegramBrandRow) {
                        $this->telegramItemTable->update($set, $primaryKey);
                    } else {
                        $this->telegramItemTable->insert(array_replace($set, $primaryKey));
                    }

                    $this->replyWithMessage([
                        'text' => 'Successful subscribed to ' . $brandRow->name
                    ]);
                }
            } else {
                $this->replyWithMessage([
                    'text' => 'Brand "' . $arguments . '" not found'
                ]);
            }
        } else {
            $this->replyWithMessage([
                'text' => "Plase, type brand name. For Example /new BMW"
            ]);
        }
    }
}
