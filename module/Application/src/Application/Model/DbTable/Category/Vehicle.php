<?php

namespace Application\Model\DbTable\Category;

use Zend_Db_Table;

class Vehicle extends Zend_Db_Table
{
    protected $_name = 'category_car';
    protected $_primary = ['category_id', 'car_id'];
    protected $_referenceMap = [
        'Category' => [
            'columns'       => ['category_id'],
            'refTableClass' => \Application\Model\DbTable\Category::class,
            'refColumns'    => ['id']
        ],
        'Car' => [
            'columns'       => ['car_id'],
            'refTableClass' => 'Cars',
            'refColumns'    => ['id']
        ],
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => 'Users',
            'refColumns'    => ['id']
        ]
    ];
}