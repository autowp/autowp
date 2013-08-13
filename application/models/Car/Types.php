<?php

class Car_Types extends Project_Db_Table
{
    protected $_name = 'car_types';

    protected $_referenceMap = array(
        'Parent' => array(
            'columns'       => array('parent_id'),
            'refTableClass' => 'Car_Types',
            'refColumns'    => array('id')
        ),
    );
}