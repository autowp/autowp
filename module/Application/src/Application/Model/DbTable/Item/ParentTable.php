<?php

namespace Application\Model\DbTable\Item;

use Autowp\Commons\Db\Table;
use Application\Model\DbTable;

class ParentTable extends Table
{
    protected $_name = 'item_parent';
    protected $_primary = ['item_id', 'parent_id'];

    protected $_referenceMap = [
        'Car' => [
            'columns'       => ['item_id'],
            'refTableClass' => DbTable\Item::class,
            'refColumns'    => ['id']
        ],
        'Parent' => [
            'columns'       => ['parent_id'],
            'refTableClass' => DbTable\Item::class,
            'refColumns'    => ['id']
        ],
    ];
}
