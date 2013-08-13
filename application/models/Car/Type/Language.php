<?php

class Car_Type_Language extends Project_Db_Table
{
    protected $_name = 'car_type_language';

    protected $_referenceMap = array(
        'Car_Type' => array(
            'columns'       => array('car_type_id'),
            'refTableClass' => 'Car_Types',
            'refColumns'    => array('id')
        )
    );
}