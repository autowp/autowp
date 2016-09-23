<?php

class Brand_Engine extends Zend_Db_Table
{
    protected $_primary = ['brand_id', 'engine_id'];
    protected $_name = 'brand_engine';
    protected $_referenceMap = [
        'Brand' => [
            'columns'       => ['brand_id'],
            'refTableClass' => 'Brand',
            'refColumns'    => ['id']
        ],
        'Engine' => [
            'columns'       => ['car_id'],
            'refTableClass' => 'Engines',
            'refColumns'    => ['id']
        ]
    ];
}