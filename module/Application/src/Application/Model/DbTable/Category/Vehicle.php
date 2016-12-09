<?php

namespace Application\Model\DbTable\Category;

use Zend_Db_Table;

class Vehicle extends Zend_Db_Table
{
    protected $_name = 'category_item';
    protected $_primary = ['category_id', 'item_id'];
    protected $_referenceMap = [
        'Category' => [
            'columns'       => ['category_id'],
            'refTableClass' => \Application\Model\DbTable\Category::class,
            'refColumns'    => ['id']
        ],
        'Car' => [
            'columns'       => ['item_id'],
            'refTableClass' => \Application\Model\DbTable\Vehicle::class,
            'refColumns'    => ['id']
        ],
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => \Autowp\User\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ]
    ];
}
