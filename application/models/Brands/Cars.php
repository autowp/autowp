<?php

class Brands_Cars extends Zend_Db_Table
{
    protected $_primary = array('brand_id', 'car_id');
    protected $_name = 'brands_cars';
    protected $_referenceMap = array(
        'Brand' => array(
            'columns'       => array('brand_id'),
            'refTableClass' => 'Brands',
            'refColumns'    => array('id')
        ),
        'Car' => array(
            'columns'       => array('car_id'),
            'refTableClass' => 'Cars',
            'refColumns'    => array('id')
        )
    );

    const
        TYPE_DEFAULT = 0,
        TYPE_TUNING = 1,
        TYPE_SPORT = 2,
        TYPE_DESIGN = 3;
}