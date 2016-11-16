<?php

namespace Application\Model\DbTable;

use Zend_Db_Table;

class BrandAlias extends Zend_Db_Table
{
    protected $_name = 'brand_alias';
    protected $_primary = ['name'];

    protected $_referenceMap = [
        'Brand' => [
            'columns'       => ['brand_id'],
            'refTableClass' => \Application\Model\DbTable\Brand::class,
            'refColumns'    => ['id']
        ],
    ];
}
