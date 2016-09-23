<?php

use Application\Db\Table;

class Attrs_User_Values_Abstract extends Table
{
    protected $_referenceMap = array(
        'Attribute' => array(
            'columns'       => array('attribut_id'),
            'refTableClass' => 'Attrs_Attributes',
            'refColumns'    => array('id')
        ),
        'User' => array(
            'columns'       => array('user_id'),
            'refTableClass' => 'Users',
            'refColumns'    => array('id')
        ),
        'ItemType' => array(
            'columns'       => array('item_type_id'),
            'refTableClass' => 'Attrs_Item_Types',
            'refColumns'    => array('id')
        ),
    );
}