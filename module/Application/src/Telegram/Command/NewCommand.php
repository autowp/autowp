<?php

namespace Application\Telegram\Command;

use Application\Model\Item;
use Exception;
use Laminas\Db\TableGateway\TableGateway;
use Telegram\Bot\Commands\Command;

use function array_replace;
use function Autowp\Commons\currentFromResultSetInterface;

class NewCommand extends Command
{
    /** @var string */
    protected $name = "new";

    /** @var string */
    protected $description = "Subscribe to new pictures";

    private TableGateway $telegramItemTable;

    private TableGateway $itemTable;

    public function __construct(
        TableGateway $telegramItemTable,
        TableGateway $itemTable
    ) {
        $this->telegramItemTable = $telegramItemTable;
        $this->itemTable         = $itemTable;
    }

    /**
     * @inheritDoc
     * @param mixed $arguments
     * @throws Exception
     */
    public function handle($arguments)
    {
        if (! $arguments) {
            $this->replyWithMessage([
                'text' => "Plase, type brand name. For Example /new BMW",
            ]);
            return;
        }

        $brandRow = currentFromResultSetInterface($this->itemTable->select([
            'name'         => (string) $arguments,
            'item_type_id' => Item::BRAND,
        ]));

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

        $telegramBrandRow = currentFromResultSetInterface($this->telegramItemTable->select($primaryKey));

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
