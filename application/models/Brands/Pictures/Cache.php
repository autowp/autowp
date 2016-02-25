<?php

/**
 * 
 * @author autow
 * @deprecated
 */
class Brands_Pictures_Cache extends Zend_Db_Table
{
    protected $_primary = array('brand_id', 'picture_id');
    protected $_name = 'brands_pictures_cache';
    protected $_referenceMap    = array(
        'Brand' => array(
            'columns'       => array('brand_id'),
            'refTableClass' => 'Brands',
            'refColumns'    => array('id')
        ),
        'Picture' => array(
            'columns'       => array('picture_id'),
            'refTableClass' => 'Picture',
            'refColumns'    => array('id')
        )
    );
}