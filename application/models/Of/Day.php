<?php

class Of_Day extends Zend_Db_Table
{
    protected $_name = 'of_day';
    protected $_primary = 'day_date';
    protected $_referenceMap    = array(
        'Car' => array(
            'columns'       => array('car_id'),
            'refTableClass' => 'Cars',
            'refColumns'    => array('id')
        ),
    );
}