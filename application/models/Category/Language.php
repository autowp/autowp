<?php

class Category_Language extends Project_Db_Table
{
    protected $_name = 'category_language';
    protected $_primary = array('category_id', 'language');

    protected $_referenceMap = array(
        'Category' => array(
            'columns'       => array('category_id'),
            'refTableClass' => 'Category',
            'refColumns'    => array('id')
        )
    );
}