<?php

class User_Car_Subscribe extends Project_Db_Table
{
    protected $_name = 'user_car_subscribe';
    protected $_primary = array('user_id', 'car_id');

    protected $_referenceMap = array(
        'User' => array(
            'columns'       => array('user_id'),
            'refTableClass' => 'Users',
            'refColumns'    => array('id')
        ),
        'Car' => array(
            'columns'       => array('car_id'),
            'refTableClass' => 'Cars',
            'refColumns'    => array('id')
        )
    );

    public function subscribe(Users_Row $user, Car_Row $car)
    {
        $row = $this->fetchRow(array(
               'user_id = ?' => $user->id,
               'car_id = ?'  => $car->id
        ));
        if (!$row) {
            $this->insert(array(
                'user_id' => $user->id,
                'car_id'  => $car->id
            ));
        }
    }

    public function unsubscribe(Users_Row $user, Car_Row $car)
    {
        $row = $this->fetchRow(array(
            'user_id = ?' => $user->id,
            'car_id = ?'  => $car->id
        ));
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