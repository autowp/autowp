<?php

class Attrs_Zones extends Project_Db_Table
{
    protected $_name = 'attrs_zones';
    protected $_rowClass = 'Attrs_Zone_Row';
    protected $_referenceMap = array(
        'Item_Type' => array(
            'columns'       => array('item_type_id'),
            'refTableClass' => 'Attrs_Item_Types',
            'refColumns'    => array('id')
        )
    );
}