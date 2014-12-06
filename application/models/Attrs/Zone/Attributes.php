<?php

class Attrs_Zone_Attributes extends Project_Db_Table
{
    protected $_name = 'attrs_zone_attributes';
    protected $_primary = array('zone_id', 'attribute_id');
    protected $_referenceMap = array(
        'Zone' => array(
            'columns'       => array('zone_id'),
            'refTableClass' => 'Attrs_Zones',
            'refColumns'    => array('id')
        ),
        'Attribute' => array(
            'columns'       => array('attribute_id'),
            'refTableClass' => 'Attrs_Attributes',
            'refColumns'    => array('id')
        ),
    );
}