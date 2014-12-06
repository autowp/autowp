<?php

class Attrs_Values extends Project_Db_Table
{
    protected $_name = 'attrs_values';
    protected $_referenceMap = array(
        'Attribute' => array(
            'columns'       => array('attribut_id'),
            'refTableClass' => 'Attrs_Attributes',
            'refColumns'    => array('id')
        ),
        'ItemType' => array(
            'columns'       => array('item_type_id'),
            'refTableClass' => 'Attrs_Item_Types',
            'refColumns'    => array('id')
        ),
    );
}