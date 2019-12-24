<?php

namespace Application\Telegram\Command;

use Telegram\Bot\Commands\Command;
use Zend\Db\TableGateway\TableGateway;
use Zend\Math\Rand;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;

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
     * @var MessageService
     */
    private $message;

    /**
     * @var TableGateway
     */
    private $telegramChatTable;

    /**
     * @var User
     */
    private $userModel;

    public function __construct(MessageService $message, TableGateway $telegramChatTable, User $userModel)
    {
        $this->message = $message;
        $this->telegramChatTable = $telegramChatTable;
        $this->userModel = $userModel;
    }

    /**
     * @inheritdoc
     */
    public function handle($arguments)
    {
        $args = preg_split('|[[:space:]]+|', trim($arguments));
        if ($args[0] == '') {
            $args = [];
        }

        $chatId = (int)$this->getUpdate()->getMessage()->getChat()->getId();

        $primaryKey = [
            'chat_id' => $chatId
        ];

        $telegramChatRow = $this->telegramChatTable->select($primaryKey)->current();

        if (count($args) <= 0) {
            if (! $telegramChatRow || ! $telegramChatRow['user_id']) {
                $this->replyWithMessage([
                    'disable_web_page_preview' => true,
                    'text' => 'Use this command to identify you as autowp.ru user.' . PHP_EOL .
                              'For example type "/me 12345" to identify you as user number 12345'
                ]);
                return;
            }

            $userRow = $this->userModel->getRow((int)$telegramChatRow['user_id']);

            $this->replyWithMessage([
                'disable_web_page_preview' => true,
                'text' => 'You identified as ' . $userRow['name']
            ]);

            return;
        }
        $userId = (int)$args[0];

        $userRow = $this->userModel->getRow($userId);

        if (! $userRow) {
            $this->replyWithMessage([
                'text' => 'User "' . $args[0] . '" not found'
            ]);
            return;
        }

        if (count($args) == 1) {
            $token = Rand::getString(20);

            $set = ['token' => $token];
            if ($telegramChatRow) {
                $this->telegramChatTable->update($set, $primaryKey);
            } else {
                $this->telegramChatTable->insert(array_replace($set, $primaryKey));
            }

            $command = '/me ' . $userRow['id'] . ' ' . $token;
            $message = "To complete identifications type `$command` to @autowp_bot";

            $this->message->send(null, $userRow['id'], $message);

            $this->replyWithMessage([
                'text' => 'Check your personal messages / system notifications'
            ]);
            return;
        }

        $token = (string)$args[1];

        if (! $telegramChatRow || strcmp($telegramChatRow['token'], $token) != 0) {
            $command = '/me ' . $userRow['id'];
            $this->replyWithMessage([
                'text' => "Token not matched. Try again with `$command`"
            ]);
            return;
        }

        $this->telegramChatTable->update([
            'user_id' => $userRow['id'],
            'token'   => null
        ], $primaryKey);

        $this->replyWithMessage([
            'text' => "Complete. Nice to see you, `{$userRow['name']}`"
        ]);
    }
}
