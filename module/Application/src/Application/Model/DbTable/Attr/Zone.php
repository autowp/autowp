<?php

namespace Application\Model\DbTable\Attr;

use Application\Db\Table;

class Zone extends Table
{
    protected $_name = 'attrs_zones';
    protected $_rowClass = \Application\Model\DbTable\Attr\ZoneRow::class;
    protected $_referenceMap = [
        'Item_Type' => [
            'columns'       => ['item_type_id'],
            'refTableClass' => \Application\Model\DbTable\Attr\ItemType::class,
            'refColumns'    => ['id']
        ]
    ];
}
