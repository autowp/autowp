<?php

namespace Application\Telegram\Command;

use Telegram\Bot\Commands\Command;

use Application\Model\DbTable\Brand as BrandTable;
use Application\Model\DbTable\Telegram\Brand as TelegramBrand;
use Application\Model\DbTable\Telegram\Chat as TelegramChat;

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

        $chatTable = new TelegramChat();

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
            $brandTable = new BrandTable();

            $brandRow = $brandTable->fetchRow([
                'caption = ?' => (string)$arguments
            ]);

            if ($brandRow) {
                $telegramBrandTable = new TelegramBrand();
                $telegramBrandRow = $telegramBrandTable->fetchRow([
                    'brand_id = ?' => $brandRow->id,
                    'chat_id  = ?' => $chatId
                ]);

                if ($telegramBrandRow && $telegramBrandRow->inbox) {
                    $telegramBrandRow->inbox = 0;
                    $telegramBrandRow->save();
                    $this->replyWithMessage([
                        'text' => 'Successful unsubscribed from ' . $brandRow->caption
                    ]);
                } else {
                    if (! $telegramBrandRow) {
                        $telegramBrandRow = $telegramBrandTable->createRow([
                            'brand_id' => $brandRow->id,
                            'chat_id'  => $chatId
                        ]);
                    }
                    $telegramBrandRow->inbox = 1;
                    $telegramBrandRow->save();
                    $this->replyWithMessage([
                        'text' => 'Successful subscribed to ' . $brandRow->caption
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
