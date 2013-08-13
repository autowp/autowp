<?php

class Brand_Alias extends Zend_Db_Table
{
    protected $_name = 'brand_alias';
    protected $_primary = array('name');

    protected $_referenceMap    = array(
        'Brand' => array(
            'columns'       => array('brand_id'),
            'refTableClass' => 'Brands',
            'refColumns'    => array('id')
        ),
	);
}