<?php

namespace Application\Telegram\Command;

use Telegram\Bot\Commands\Command;

use Application\Model\DbTable;

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

        if ($arguments) {
            $itemTable = new DbTable\Item();

            $brandRow = $itemTable->fetchRow([
                'name = ?'         => (string)$arguments,
                'item_type_id = ?' => DbTable\Item\Type::BRAND
            ]);

            if ($brandRow) {
                $telegramBrandTable = new DbTable\Telegram\Brand();
                $telegramBrandRow = $telegramBrandTable->fetchRow([
                    'item_id = ?' => $brandRow->id,
                    'chat_id = ?' => $chatId
                ]);

                if ($telegramBrandRow && $telegramBrandRow->inbox) {
                    $telegramBrandRow->inbox = 0;
                    $telegramBrandRow->save();
                    $this->replyWithMessage([
                        'text' => 'Successful unsubscribed from ' . $brandRow->name
                    ]);
                } else {
                    if (! $telegramBrandRow) {
                        $telegramBrandRow = $telegramBrandTable->createRow([
                            'item_id' => $brandRow->id,
                            'chat_id' => $chatId
                        ]);
                    }
                    $telegramBrandRow->inbox = 1;
                    $telegramBrandRow->save();
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
                'text' => "Plase, type brand name. For Example /inbox BMW"
            ]);
        }
    }
}
