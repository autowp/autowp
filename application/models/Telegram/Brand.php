<?php

class Telegram_Brand extends Zend_Db_Table
{
    protected $_name = 'telegram_brand';
    protected $_referenceMap    = array(
        'Brand' => array(
            'columns'           => array('brand_id'),
            'refTableClass'     => 'Brands',
            'refColumns'        => array('id')
        ),
    );
}