<?php

class Car_Language extends Project_Db_Table
{
    protected $_name = 'car_language';
    protected $_primary = array('car_id', 'language');

    protected $_referenceMap = array(
        'Car' => array(
            'columns'       => array('car_id'),
            'refTableClass' => 'Cars',
            'refColumns'    => array('id')
        ),
    );
}