<?php

class Attrs_List_Options extends Project_Db_Table
{
    protected $_name = 'attrs_list_options';
    protected $_referenceMap = array(
        'Attribute' => array(
            'columns'       => array('attribute_id'),
            'refTableClass' => 'Attrs_Attributes',
            'refColumns'    => array('id')
        )
    );
}