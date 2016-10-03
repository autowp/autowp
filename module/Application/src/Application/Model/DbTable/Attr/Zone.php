<?php

use Application\Db\Table;

class Attrs_Zones extends Table
{
    protected $_name = 'attrs_zones';
    protected $_rowClass = 'Attrs_Zone_Row';
    protected $_referenceMap = [
        'Item_Type' => [
            'columns'       => ['item_type_id'],
            'refTableClass' => 'Attrs_Item_Types',
            'refColumns'    => ['id']
        ]
    ];
}