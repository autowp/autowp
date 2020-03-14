<?php

namespace Application\Telegram\Command;

use Application\Model\Item;
use Laminas\Db\TableGateway\TableGateway;
use Telegram\Bot\Commands\Command;

use function array_replace;

class NewCommand extends Command
{
    /** @var string */
    protected $name = "new";

    /** @var string */
    protected $description = "Subscribe to new pictures";

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
     * @inheritDoc
     * @param mixed $arguments
     */
    public function handle($arguments)
    {
        if (! $arguments) {
            $this->replyWithMessage([
                'text' => "Plase, type brand name. For Example /new BMW",
            ]);
            return;
        }

        $brandRow = $this->itemTable->select([
            'name'         => (string) $arguments,
            'item_type_id' => Item::BRAND,
        ])->current();

        if (! $brandRow) {
            $this->replyWithMessage([
                'text' => 'Brand "' . $arguments . '" not found',
            ]);
            return;
        }

        $chatId = (int) $this->getUpdate()->getMessage()->getChat()->getId();

        $primaryKey = [
            'item_id' => $brandRow['id'],
            'chat_id' => $chatId,
        ];

        $telegramBrandRow = $this->telegramItemTable->select($primaryKey)->current();

        if ($telegramBrandRow && $telegramBrandRow['new']) {
            $this->telegramItemTable->update(['new' => 0], $primaryKey);
            $this->replyWithMessage([
                'text' => 'Successful unsubscribed from ' . $brandRow['name'],
            ]);
            return;
        }

        $set = ['new' => 1];
        if ($telegramBrandRow) {
            $this->telegramItemTable->update($set, $primaryKey);
        } else {
            $this->telegramItemTable->insert(array_replace($set, $primaryKey));
        }

        $this->replyWithMessage([
            'text' => 'Successful subscribed to ' . $brandRow['name'],
        ]);
    }
}
