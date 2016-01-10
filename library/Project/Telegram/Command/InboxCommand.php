<?php

namespace Project\Telegram\Command;

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

            $brandRow = $brandTable->fetchRow(array(
                'caption = ?' => (string)$arguments
            ));

            if ($brandRow) {

                $chatId = (int)$this->getUpdate()->getMessage()->getChat()->getId();

                $telegramBrandTable = new \Telegram_Brand();
                $telegramBrandRow = $telegramBrandTable->fetchRow(array(
                    'brand_id = ?' => $brandRow->id,
                    'chat_id  = ?' => $chatId
                ));

                if ($telegramBrandRow) {
                    $telegramBrandRow->delete();
                    $this->replyWithMessage(array(
                        'text' => 'Successful unsubscribed from ' . $brandRow->caption
                    ));
                } else {
                    $telegramBrandRow = $telegramBrandTable->createRow(array(
                        'brand_id' => $brandRow->id,
                        'chat_id'  => $chatId
                    ));
                    $telegramBrandRow->save();
                    $this->replyWithMessage(array(
                        'text' => 'Successful subscribed to ' . $brandRow->caption
                    ));
                }

            } else {
                $this->replyWithMessage(array(
                    'text' => 'Brand "' . $arguments . '" not found'
                ));
            }

        } else {
            $this->replyWithMessage(array(
                'text' => "Plase, type brand name. For Example /inbox BMW"
            ));
        }
    }
}