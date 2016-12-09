<?php

namespace Application\Model\DbTable\Attr;

use Application\Db\Table;

class ValueAbstract extends Table
{
    protected $_referenceMap = [
        'Attribute' => [
            'columns'       => ['attribut_id'],
            'refTableClass' => Attribute::class,
            'refColumns'    => ['id']
        ]
    ];
    protected $_primary = ['attribute_id', 'item_id'];
}
