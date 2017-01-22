<?php

namespace Application\Model\DbTable\Item;

use Autowp\Commons\Db\Table;

class Link extends Table
{
    protected $_name = 'links';
    protected $_primary = 'id';

    protected $_referenceMap = [
        'Item' => [
            'columns'       => ['item_id'],
            'refTableClass' => Application\Model\DbTable\Item::class,
            'refColumns'    => ['id']
        ]
    ];
}
