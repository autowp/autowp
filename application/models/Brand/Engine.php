<?php

class Brand_Engine extends Zend_Db_Table
{
    protected $_primary = array('brand_id', 'engine_id');
    protected $_name = 'brand_engine';
    protected $_referenceMap = array(
        'Brand' => array(
            'columns'       => array('brand_id'),
            'refTableClass' => 'Brand',
            'refColumns'    => array('id')
        ),
        'Engine' => array(
            'columns'       => array('car_id'),
            'refTableClass' => 'Engines',
            'refColumns'    => array('id')
        )
    );
}