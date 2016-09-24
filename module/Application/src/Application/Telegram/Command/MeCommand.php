<?php

namespace Application\Telegram\Command;

use Telegram\Bot\Commands\Command;

class MeCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "me";

    /**
     * @var string Command Description
     */
    protected $description = "Command to identify you as autowp.ru user";

    /**
     * @inheritdoc
     */
    public function handle($arguments)
    {
        $args = split('[[:space:]]', trim($arguments));
        if ($args[0] == '') {
            $args = [];
        }

        $chatId = (int)$this->getUpdate()->getMessage()->getChat()->getId();

        $telegramChatTable = new \Telegram_Chat();

        $telegramChatRow = $telegramChatTable->fetchRow([
            'chat_id = ?' => $chatId
        ]);

        if (count($args) <= 0) {
            if (!$telegramChatRow || !$telegramChatRow->user_id) {
                $this->replyWithMessage([
                    'disable_web_page_preview' => true,
                    'text' => 'Use this command to identify you as autowp.ru user.' . PHP_EOL .
                              'For example type "/me 12345" to identify you as user number 12345'
                ]);
            } else {

                $userTable = new \Users();
                $userRow = $userTable->find($telegramChatRow->user_id)->current();

                $this->replyWithMessage([
                    'disable_web_page_preview' => true,
                    'text' => 'You identified as ' . $userRow->name
                ]);
            }
        } else {

            $userId = (int)$args[0];

            $userTable = new \Users();

            $userRow = $userTable->find($userId)->current();

            if (!$userRow) {

                $this->replyWithMessage([
                    'text' => 'User "' . $args[0] . '" not found'
                ]);

            } else {

                if (!$telegramChatRow) {
                    $telegramChatRow = $telegramChatTable->createRow([
                        'chat_id' => $chatId
                    ]);
                    $telegramChatRow->save();
                }

                if (count($args) == 1) {
                    $token = md5(uniqid());

                    $telegramChatRow->token = $token;
                    $telegramChatRow->save();

                    $command = '/me ' . $userRow->id . ' ' . $token;
                    $userRow->sendPersonalMessage(null, "To complete identifications type `$command` to @autowp_bot");

                    $this->replyWithMessage([
                        'text' => 'Check you personal messages / system notifications'
                    ]);

                } else {

                    $token = (string)$args[1];

                    if (strcmp($telegramChatRow->token, $token) != 0) {
                        $command = '/me ' . $userRow->id;
                        $this->replyWithMessage([
                            'text' => "Token not matched. Try again with `$command`"
                        ]);
                    } else {

                        $telegramChatRow->user_id = $userRow->id;
                        $telegramChatRow->token = null;
                        $telegramChatRow->save();

                        $this->replyWithMessage([
                            'text' => "Complete. Nice to see you, `{$userRow->name}`"
                        ]);

                    }
                }
            }
        }


    }
}