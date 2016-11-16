<?php

namespace Application\Model\DbTable\Attr;

use Application\Db\Table;

class Attribute extends Table
{
    protected $_name = 'attrs_attributes';
    protected $_rowClass = AttributeRow::class;
    protected $_referenceMap = [
        'Parent' => [
            'columns'       => ['parent_id'],
            'refTableClass' => self::class,
            'refColumns'    => ['id']
        ],
        'Unit' => [
            'columns'       => ['unit_id'],
            'refTableClass' => Unit::class,
            'refColumns'    => ['id']
        ],
        'Type' => [
            'columns'       => ['type_id'],
            'refTableClass' => Type::class,
            'refColumns'    => ['id']
        ]
    ];
}
