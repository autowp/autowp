<?php

use Application\Db\Table;

class Attrs_List_Options extends Table
{
    protected $_name = 'attrs_list_options';
    protected $_referenceMap = [
        'Attribute' => [
            'columns'       => ['attribute_id'],
            'refTableClass' => 'Attrs_Attributes',
            'refColumns'    => ['id']
        ]
    ];
}