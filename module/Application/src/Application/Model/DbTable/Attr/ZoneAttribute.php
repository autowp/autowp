<?php

namespace Application\Model\DbTable\Attr;

use Application\Db\Table;

class ZoneAttribute extends Table
{
    protected $_name = 'attrs_zone_attributes';
    protected $_primary = ['zone_id', 'attribute_id'];
    protected $_referenceMap = [
        'Zone' => [
            'columns'       => ['zone_id'],
            'refTableClass' => Zone::class,
            'refColumns'    => ['id']
        ],
        'Attribute' => [
            'columns'       => ['attribute_id'],
            'refTableClass' => Attribute::class,
            'refColumns'    => ['id']
        ],
    ];
}