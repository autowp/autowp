<?php

use Application\Db\Table;

class Car_Types extends Table
{
    protected $_name = 'car_types';

    protected $_referenceMap = [
        'Parent' => [
            'columns'       => ['parent_id'],
            'refTableClass' => 'Car_Types',
            'refColumns'    => ['id']
        ],
    ];
}