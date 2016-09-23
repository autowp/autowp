<?php

use Application\Db\Table;

class Factory_Car extends Table
{
    protected $_name = 'factory_car';
    protected $_primary = ['factory_id', 'car_id'];

    protected $_referenceMap = [
        'Factory' => [
            'columns'       => ['factory_id'],
            'refTableClass' => 'Factory',
            'refColumns'    => ['id']
        ],
        'Car' => [
            'columns'       => ['car_id'],
            'refTableClass' => 'Cars',
            'refColumns'    => ['id']
        ],
    ];
}