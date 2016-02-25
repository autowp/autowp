<?php

class Articles_Brands_Cache extends Zend_Db_Table
{
    protected $_primary = array('article_id', 'brand_id');
    protected $_name = 'articles_brands_cache';
    protected $_referenceMap    = array(
        'Article' => array(
            'columns'           => array('article_id'),
            'refTableClass'     => 'Articles',
            'refColumns'        => array('id')
        ),
        'Brand' => array(
            'columns'           => array('brand_id'),
            'refTableClass'     => 'Brands',
            'refColumns'        => array('id')
        )
    );
}