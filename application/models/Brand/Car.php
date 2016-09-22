<?php

class Brand_Car extends Zend_Db_Table
{
    protected $_primary = ['brand_id', 'car_id'];
    protected $_name = 'brands_cars';
    protected $_referenceMap = [
        'Brand' => [
            'columns'       => ['brand_id'],
            'refTableClass' => 'Brands',
            'refColumns'    => ['id']
        ],
        'Car' => [
            'columns'       => ['car_id'],
            'refTableClass' => 'Cars',
            'refColumns'    => ['id']
        ]
    ];

    const
        TYPE_DEFAULT = 0,
        TYPE_TUNING = 1,
        TYPE_SPORT = 2,
        TYPE_DESIGN = 3;
}