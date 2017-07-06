<?php

namespace Application\Model;

use Zend\Db\TableGateway\TableGateway;

use Autowp\User\Model\DbTable\User;

class UserItemSubscribe
{
    /**
     * @var TableGateway
     */
    private $table;

    public function __construct(TableGateway $table)
    {
        $this->table = $table;
    }

    public function subscribe(int $userId, int $itemId)
    {
        $primaryKey = [
            'user_id' => $userId,
            'item_id' => $itemId
        ];

        $row = $this->table->select($primaryKey)->current();
        if (! $row) {
            $this->table->insert($primaryKey);
        }
    }

    public function unsubscribe(int $userId, int $itemId)
    {
        $this->table->delete([
            'user_id' => $userId,
            'item_id' => $itemId
        ]);
    }

    public function getItemSubscribers(int $itemId)
    {
        $uTable = new User();

        return $uTable->fetchAll(
            $uTable->select(true)
                ->join('user_item_subscribe', 'users.id = user_item_subscribe.user_id', null)
                ->where('user_item_subscribe.item_id = ?', $itemId)
        );
    }

    public function unsubscribeAll(int $userId)
    {
        $this->table->delete([
            'user_id' => $userId
        ]);
    }

    public function isSubscribed(int $userId, int $itemId): bool
    {
        return (bool) $this->table->select([
            'user_id' => $userId,
            'item_id' => $itemId
        ])->current();
    }
}