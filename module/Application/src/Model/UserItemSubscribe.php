<?php

namespace Application\Model;

use Autowp\User\Model\User;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\TableGateway\TableGateway;

use function Autowp\Commons\currentFromResultSetInterface;

class UserItemSubscribe
{
    private TableGateway $table;

    private User $userModel;

    public function __construct(TableGateway $table, User $userModel)
    {
        $this->table     = $table;
        $this->userModel = $userModel;
    }

    public function subscribe(int $userId, int $itemId): void
    {
        $sql = '
            INSERT IGNORE INTO user_item_subscribe (user_id, item_id)
            VALUES (:user_id, :item_id)
        ';
        /** @var Adapter $adapter */
        $adapter = $this->table->getAdapter();
        $adapter->query($sql, [
            'user_id' => $userId,
            'item_id' => $itemId,
        ]);
    }

    public function unsubscribe(int $userId, int $itemId): void
    {
        $this->table->delete([
            'user_id' => $userId,
            'item_id' => $itemId,
        ]);
    }

    public function getItemSubscribers(int $itemId): ResultSetInterface
    {
        $table = $this->userModel->getTable();

        return $table->selectWith(
            $table->getSql()->select()
                ->join('user_item_subscribe', 'users.id = user_item_subscribe.user_id', [])
                ->where(['user_item_subscribe.item_id' => $itemId])
        );
    }

    public function unsubscribeAll(int $userId): void
    {
        $this->table->delete([
            'user_id' => $userId,
        ]);
    }

    public function isSubscribed(int $userId, int $itemId): bool
    {
        return (bool) currentFromResultSetInterface($this->table->select([
            'user_id' => $userId,
            'item_id' => $itemId,
        ]));
    }
}
