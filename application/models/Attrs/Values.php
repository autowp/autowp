<?php

use Application\Db\Table;

class Attrs_Values extends Table
{
    protected $_name = 'attrs_values';
    protected $_referenceMap = [
        'Attribute' => [
            'columns'       => ['attribut_id'],
            'refTableClass' => 'Attrs_Attributes',
            'refColumns'    => ['id']
        ],
        'ItemType' => [
            'columns'       => ['item_type_id'],
            'refTableClass' => 'Attrs_Item_Types',
            'refColumns'    => ['id']
        ],
    ];
}