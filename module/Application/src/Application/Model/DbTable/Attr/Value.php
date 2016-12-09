<?php

namespace Application\Model\DbTable\Attr;

use Application\Db\Table;

class Value extends Table
{
    protected $_name = 'attrs_values';
    protected $_referenceMap = [
        'Attribute' => [
            'columns'       => ['attribute_id'],
            'refTableClass' => Attribute::class,
            'refColumns'    => ['id']
        ]
    ];
    protected $_primary = ['attribute_id', 'item_id'];
}
