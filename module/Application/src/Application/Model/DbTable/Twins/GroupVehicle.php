<?php

namespace Application\Model\DbTable\Twins;

use Zend_Db_Table;

class GroupVehicle extends Zend_Db_Table
{
    protected $_primary = ['twins_group_id', 'car_id'];
    protected $_name = 'twins_groups_cars';
    protected $_referenceMap = [
        'Group' => [
            'columns'       => ['twins_group_id'],
            'refTableClass' => \Application\Model\DbTable\Twins\Group::class,
            'refColumns'    => ['id']
        ],
        'Car' => [
            'columns'       => ['car_id'],
            'refTableClass' => \Application\Model\DbTable\Vehicle::class,
            'refColumns'    => ['id']
        ]
    ];
}