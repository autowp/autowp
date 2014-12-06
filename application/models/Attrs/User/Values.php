<?php

class Attrs_User_Values extends Project_Db_Table
{
    protected $_name = 'attrs_user_values';
    protected $_referenceMap    = array(
        'Attribute' => array(
            'columns'       => array('attribute_id'),
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
        )
    );
}