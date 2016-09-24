<?php

namespace Application\Telegram\Command;

use Telegram\Bot\Commands\Command;

class InboxCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "inbox";

    /**
     * @var string Command Description
     */
    protected $description = "Subscribe to inbox command";

    /**
     * @inheritdoc
     */
    public function handle($arguments)
    {
        if ($arguments) {
            $brandTable = new \Brands();

            $brandRow = $brandTable->fetchRow([
                'caption = ?' => (string)$arguments
            ]);

            if ($brandRow) {

                $chatId = (int)$this->getUpdate()->getMessage()->getChat()->getId();

                $telegramBrandTable = new \Telegram_Brand();
                $telegramBrandRow = $telegramBrandTable->fetchRow([
                    'brand_id = ?' => $brandRow->id,
                    'chat_id  = ?' => $chatId
                ]);

                if ($telegramBrandRow) {
                    $telegramBrandRow->delete();
                    $this->replyWithMessage([
                        'text' => 'Successful unsubscribed from ' . $brandRow->caption
                    ]);
                } else {
                    $telegramBrandRow = $telegramBrandTable->createRow([
                        'brand_id' => $brandRow->id,
                        'chat_id'  => $chatId
                    ]);
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