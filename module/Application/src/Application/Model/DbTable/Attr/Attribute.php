<?php

namespace Application\Model\DbTable\Attr;

use Autowp\Commons\Db\Table;

class Attribute extends Table
{
    protected $_name = 'attrs_attributes';
    protected $_rowClass = AttributeRow::class;
    protected $_referenceMap = [
        'Parent' => [
            'columns'       => ['parent_id'],
            'refTableClass' => self::class,
            'refColumns'    => ['id']
        ]
    ];
}
