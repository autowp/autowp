<?php

namespace Application\Telegram\Command;

use Telegram\Bot\Commands\Command;

use Application\Model\DbTable\Brand as BrandTable;
use Application\Model\DbTable\Telegram\Brand as TelegramBrand;

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
     * @inheritdoc
     */
    public function handle($arguments)
    {
        if ($arguments) {
            $brandTable = new BrandTable();

            $brandRow = $brandTable->fetchRow([
                'name = ?' => (string)$arguments
            ]);

            if ($brandRow) {
                $chatId = (int)$this->getUpdate()->getMessage()->getChat()->getId();

                $telegramBrandTable = new TelegramBrand();
                $telegramBrandRow = $telegramBrandTable->fetchRow([
                    'brand_id = ?' => $brandRow->id,
                    'chat_id  = ?' => $chatId
                ]);

                if ($telegramBrandRow && $telegramBrandRow->new) {
                    $telegramBrandRow->new = 0;
                    $telegramBrandRow->save();
                    $this->replyWithMessage([
                        'text' => 'Successful unsubscribed from ' . $brandRow->name
                    ]);
                } else {
                    if (! $telegramBrandRow) {
                        $telegramBrandRow = $telegramBrandTable->createRow([
                            'brand_id' => $brandRow->id,
                            'chat_id'  => $chatId
                        ]);
                    }
                    $telegramBrandRow->new = 1;
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
                'text' => "Plase, type brand name. For Example /new BMW"
            ]);
        }
    }
}
