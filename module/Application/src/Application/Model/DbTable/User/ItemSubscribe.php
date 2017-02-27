<?php

namespace Application\Model\DbTable\User;

use Autowp\User\Model\DbTable\User;
use Autowp\User\Model\DbTable\User\Row as UserRow;

use Autowp\Commons\Db\Table;
use Application\Model\DbTable;

class ItemSubscribe extends Table
{
    protected $_name = 'user_item_subscribe';
    protected $_primary = ['user_id', 'item_id'];

    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => User::class,
            'refColumns'    => ['id']
        ],
        'Car' => [
            'columns'       => ['item_id'],
            'refTableClass' => Application\Model\DbTable\Item::class,
            'refColumns'    => ['id']
        ]
    ];

    public function subscribe(UserRow $user, DbTable\Item\Row $car)
    {
        $row = $this->fetchRow([
           'user_id = ?' => $user->id,
           'item_id = ?' => $car->id
        ]);
        if (! $row) {
            $this->insert([
                'user_id' => $user->id,
                'item_id' => $car->id
            ]);
        }
    }

    public function unsubscribe(UserRow $user, DbTable\Item\Row $car)
    {
        $row = $this->fetchRow([
            'user_id = ?' => $user->id,
            'item_id = ?' => $car->id
        ]);
        if ($row) {
            $row->delete();
        }
    }

    public function getItemSubscribers(DbTable\Item\Row $car)
    {
        $uTable = new User();

        return $uTable->fetchAll(
            $uTable->select(true)
                ->join('user_item_subscribe', 'users.id = user_item_subscribe.user_id', null)
                ->where('user_item_subscribe.item_id = ?', $car->id)
        );
    }
    
    public function unsubscribeAll($userId)
    {
        $this->delete([
            'user_id = ?' => $userId
        ]);
    }
}
