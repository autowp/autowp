<?php

use Application\Db\Table;

class User_Car_Subscribe extends Table
{
    protected $_name = 'user_car_subscribe';
    protected $_primary = ['user_id', 'car_id'];

    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => 'Users',
            'refColumns'    => ['id']
        ],
        'Car' => [
            'columns'       => ['car_id'],
            'refTableClass' => 'Cars',
            'refColumns'    => ['id']
        ]
    ];

    public function subscribe(User_Row $user, Car_Row $car)
    {
        $row = $this->fetchRow([
           'user_id = ?' => $user->id,
           'car_id = ?'  => $car->id
        ]);
        if (!$row) {
            $this->insert([
                'user_id' => $user->id,
                'car_id'  => $car->id
            ]);
        }
    }

    public function unsubscribe(User_Row $user, Car_Row $car)
    {
        $row = $this->fetchRow([
            'user_id = ?' => $user->id,
            'car_id = ?'  => $car->id
        ]);
        if ($row) {
            $row->delete();
        }
    }

    public function getCarSubscribers(Car_Row $car)
    {
        $uTable = new Users();

        return $uTable->fetchAll(
            $uTable->select(true)
                ->join('user_car_subscribe', 'users.id = user_car_subscribe.user_id', null)
                ->where('user_car_subscribe.car_id = ?', $car->id)
        );
    }
}