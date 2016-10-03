<?php

use Application\Db\Table;

class Attrs_User_Values extends Table
{
    protected $_name = 'attrs_user_values';
    protected $_referenceMap = [
        'Attribute' => [
            'columns'       => ['attribute_id'],
            'refTableClass' => 'Attrs_Attributes',
            'refColumns'    => ['id']
        ],
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => \Application\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ],
        'ItemType' => [
            'columns'       => ['item_type_id'],
            'refTableClass' => 'Attrs_Item_Types',
            'refColumns'    => ['id']
        ]
    ];
}