<?php

namespace Application\Model\DbTable\Attr;

use Autowp\Commons\Db\Table;

class ListOption extends Table
{
    protected $_name = 'attrs_list_options';
    protected $_referenceMap = [
        'Attribute' => [
            'columns'       => ['attribute_id'],
            'refTableClass' => Attribute::class,
            'refColumns'    => ['id']
        ]
    ];
}
