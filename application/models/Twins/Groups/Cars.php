<?php

class Twins_Groups_Cars extends Zend_Db_Table
{
    protected $_primary = array('twins_group_id', 'car_id');
    protected $_name = 'twins_groups_cars';
    protected $_referenceMap = array(
        'Twins_Group' => array(
            'columns'       => array('twins_group_id'),
            'refTableClass' => 'Twins_Groups',
            'refColumns'    => array('id')
        ),
        'Car' => array(
            'columns'       => array('car_id'),
            'refTableClass' => 'Cars',
            'refColumns'    => array('id')
        )
    );
}