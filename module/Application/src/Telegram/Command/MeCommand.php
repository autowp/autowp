<?php

namespace Application\Telegram\Command;

use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use Exception;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Math\Rand;
use Telegram\Bot\Commands\Command;

use function array_replace;
use function Autowp\Commons\currentFromResultSetInterface;
use function count;
use function preg_split;
use function strcmp;
use function trim;

use const PHP_EOL;

class MeCommand extends Command
{
    /** @var string */
    protected $name = "me";

    /** @var string */
    protected $description = "Command to identify you as autowp.ru user";

    private MessageService $message;

    private TableGateway $telegramChatTable;

    private User $userModel;

    public function __construct(MessageService $message, TableGateway $telegramChatTable, User $userModel)
    {
        $this->message           = $message;
        $this->telegramChatTable = $telegramChatTable;
        $this->userModel         = $userModel;
    }

    /**
     * @inheritDoc
     * @throws Exception
     * @param mixed $arguments
     */
    public function handle($arguments)
    {
        $args = preg_split('|[[:space:]]+|', trim($arguments));
        if ($args[0] === '') {
            $args = [];
        }

        $chatId = (int) $this->getUpdate()->getMessage()->getChat()->getId();

        $primaryKey = [
            'chat_id' => $chatId,
        ];

        $telegramChatRow = currentFromResultSetInterface($this->telegramChatTable->select($primaryKey));

        if (count($args) <= 0) {
            if (! $telegramChatRow || ! $telegramChatRow['user_id']) {
                $this->replyWithMessage([
                    'disable_web_page_preview' => true,
                    'text'                     => 'Use this command to identify you as autowp.ru user.' . PHP_EOL
                              . 'For example type "/me 12345" to identify you as user number 12345',
                ]);
                return;
            }

            $userRow = $this->userModel->getRow((int) $telegramChatRow['user_id']);

            if ($userRow) {
                $this->replyWithMessage([
                    'disable_web_page_preview' => true,
                    'text'                     => 'You identified as ' . $userRow['name'],
                ]);
            }

            return;
        }
        $userId = (int) $args[0];

        $userRow = $this->userModel->getRow($userId);

        if (! $userRow) {
            $this->replyWithMessage([
                'text' => 'User "' . $args[0] . '" not found',
            ]);
            return;
        }

        if (count($args) === 1) {
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
                'text' => 'Check your personal messages / system notifications',
            ]);
            return;
        }

        $token = (string) $args[1];

        if (! $telegramChatRow || strcmp($telegramChatRow['token'], $token) !== 0) {
            $command = '/me ' . $userRow['id'];
            $this->replyWithMessage([
                'text' => "Token not matched. Try again with `$command`",
            ]);
            return;
        }

        $this->telegramChatTable->update([
            'user_id' => $userRow['id'],
            'token'   => null,
        ], $primaryKey);

        $this->replyWithMessage([
            'text' => "Complete. Nice to see you, `{$userRow['name']}`",
        ]);
    }
}
