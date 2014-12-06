<?php

class Attrs_Attributes extends Project_Db_Table
{
    protected $_name = 'attrs_attributes';
    protected $_rowClass = 'Attrs_Attributes_Row';
    protected $_referenceMap = array(
        'Parent' => array(
            'columns'       => array('parent_id'),
            'refTableClass' => 'Attrs_Attributes',
            'refColumns'    => array('id')
        ),
        'Unit' => array(
            'columns'       => array('unit_id'),
            'refTableClass' => 'Attrs_Units',
            'refColumns'    => array('id')
        ),
        'Type' => array(
            'columns'       => array('type_id'),
            'refTableClass' => 'Attrs_Types',
            'refColumns'    => array('id')
        )
    );
}