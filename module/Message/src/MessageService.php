<?php

namespace Autowp\Message;

use Application\Service\TelegramService;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function mb_strlen;
use function trim;

/**
 * @todo Unlink from Telegram
 */
class MessageService
{
    private TableGateway $table;

    public const MAX_TEXT = 2000;

    private TelegramService $telegram;

    public function __construct(TelegramService $telegram, TableGateway $table)
    {
        $this->table    = $table;
        $this->telegram = $telegram;
    }

    /**
     * @throws Exception
     */
    public function send(?int $fromId, int $toId, string $message): void
    {
        $message   = trim($message);
        $msgLength = mb_strlen($message);

        if ($msgLength <= 0) {
            throw new Exception('Message is empty');
        }

        if ($msgLength > self::MAX_TEXT) {
            throw new Exception('Message is too long');
        }

        $this->table->insert([
            'from_user_id' => $fromId ? $fromId : null,
            'to_user_id'   => $toId,
            'contents'     => $message,
            'add_datetime' => new Sql\Expression('NOW()'),
            'readen'       => 0,
        ]);

        $this->telegram->notifyMessage($fromId, $toId, $message);
    }

    public function recycle(): int
    {
        return $this->table->delete([
            'deleted_by_to',
            'deleted_by_from OR from_user_id IS NULL',
        ]);
    }

    public function recycleSystem(): int
    {
        return $this->table->delete([
            'from_user_id is null',
            'add_datetime < date_sub(now(), interval 6 month)',
        ]);
    }
}
