<?php

use Application\Db\Table;

class Attrs_Attributes extends Table
{
    protected $_name = 'attrs_attributes';
    protected $_rowClass = 'Attrs_Attributes_Row';
    protected $_referenceMap = [
        'Parent' => [
            'columns'       => ['parent_id'],
            'refTableClass' => 'Attrs_Attributes',
            'refColumns'    => ['id']
        ],
        'Unit' => [
            'columns'       => ['unit_id'],
            'refTableClass' => 'Attrs_Units',
            'refColumns'    => ['id']
        ],
        'Type' => [
            'columns'       => ['type_id'],
            'refTableClass' => 'Attrs_Types',
            'refColumns'    => ['id']
        ]
    ];
}