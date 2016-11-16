<?php

namespace Application\Model\DbTable;

use Zend_Db_Table;

class BrandCar extends Zend_Db_Table
{
    protected $_primary = ['brand_id', 'car_id'];
    protected $_name = 'brands_cars';
    protected $_referenceMap = [
        'Brand' => [
            'columns'       => ['brand_id'],
            'refTableClass' => \Application\Model\DbTable\Brand::class,
            'refColumns'    => ['id']
        ],
        'Car' => [
            'columns'       => ['car_id'],
            'refTableClass' => \Application\Model\DbTable\Vehicle::class,
            'refColumns'    => ['id']
        ]
    ];

    const
        TYPE_DEFAULT = 0,
        TYPE_TUNING = 1,
        TYPE_SPORT = 2,
        TYPE_DESIGN = 3;

    const MAX_CATNAME = 70;
}
