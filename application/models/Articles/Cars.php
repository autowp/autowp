<?php

class Articles_Cars extends Zend_Db_Table
{
    protected $_primary = array('article_id', 'car_id');
    protected $_name = 'articles_cars';
    protected $_referenceMap    = array(
        'Article' => array(
            'columns'           => array('article_id'),
            'refTableClass'     => 'Articles',
            'refColumns'        => array('id')
        ),
        'Car' => array(
            'columns'           => array('car_id'),
            'refTableClass'     => 'Cars',
            'refColumns'        => array('id')
        )
    );
}