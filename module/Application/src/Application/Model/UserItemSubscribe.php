<?php

namespace Application\Model;

use Zend\Db\TableGateway\TableGateway;

use Autowp\User\Model\User;

class UserItemSubscribe
{
    /**
     * @var TableGateway
     */
    private $table;

    /**
     * @var User
     */
    private $userModel;

    public function __construct(TableGateway $table, User $userModel)
    {
        $this->table = $table;
        $this->userModel = $userModel;
    }

    public function subscribe(int $userId, int $itemId)
    {
        $this->table->getAdapter()->query('
            INSERT IGNORE INTO user_item_subscribe (user_id, item_id) 
            VALUES (:user_id, :item_id)
        ', [
            'user_id' => $userId,
            'item_id' => $itemId
        ]);
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
        $table = $this->userModel->getTable();

        return $table->selectWith(
            $table->getSql()->select()
                ->join('user_item_subscribe', 'users.id = user_item_subscribe.user_id', [])
                ->where(['user_item_subscribe.item_id' => $itemId])
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
