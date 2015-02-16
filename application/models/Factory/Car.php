<?php

class Factory_Car extends Project_Db_Table
{
    protected $_name = 'factory_car';
    protected $_primary = array('factory_id', 'car_id');

    protected $_referenceMap = array(
        'Factory' => array(
            'columns'       => array('factory_id'),
            'refTableClass' => 'Factory',
            'refColumns'    => array('id')
        ),
        'Car' => array(
            'columns'       => array('car_id'),
            'refTableClass' => 'Cars',
            'refColumns'    => array('id')
        ),
    );
}