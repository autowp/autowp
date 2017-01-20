<?php

namespace Application\Model\DbTable\Item;

use Zend_Db_Table;

class Alias extends Zend_Db_Table
{
    protected $_name = 'brand_alias';
    protected $_primary = ['name'];

    protected $_referenceMap = [
        'Item' => [
            'columns'       => ['item_id'],
            'refTableClass' => \Application\Model\DbTable\Item::class,
            'refColumns'    => ['id']
        ],
    ];
}
