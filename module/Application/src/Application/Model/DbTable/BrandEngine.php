<?php

namespace Application\Model\DbTable;

use Zend_Db_Table;

class BrandEngine extends Zend_Db_Table
{
    protected $_primary = ['brand_id', 'engine_id'];
    protected $_name = 'brand_engine';
    protected $_referenceMap = [
        'Brand' => [
            'columns'       => ['brand_id'],
            'refTableClass' => \Application\Model\DbTable\Brand::class,
            'refColumns'    => ['id']
        ],
        'Engine' => [
            'columns'       => ['car_id'],
            'refTableClass' => 'Engines',
            'refColumns'    => ['id']
        ]
    ];
}