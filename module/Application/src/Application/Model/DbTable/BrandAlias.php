<?php

namespace Application\Model\DbTable;

use Zend_Db_Table;

class BrandAlias extends Zend_Db_Table
{
    protected $_name = 'brand_alias';
    protected $_primary = ['name'];

    protected $_referenceMap = [
        'Item' => [
            'columns'       => ['item_id'],
            'refTableClass' => Vehicle::class,
            'refColumns'    => ['id']
        ],
    ];
}
