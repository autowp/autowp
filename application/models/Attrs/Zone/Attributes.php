<?php

use Application\Db\Table;

class Attrs_Zone_Attributes extends Table
{
    protected $_name = 'attrs_zone_attributes';
    protected $_primary = ['zone_id', 'attribute_id'];
    protected $_referenceMap = [
        'Zone' => [
            'columns'       => ['zone_id'],
            'refTableClass' => 'Attrs_Zones',
            'refColumns'    => ['id']
        ],
        'Attribute' => [
            'columns'       => ['attribute_id'],
            'refTableClass' => 'Attrs_Attributes',
            'refColumns'    => ['id']
        ],
    ];
}