<?php

class Category extends Zend_Db_Table
{
    protected $_name = 'category';
    protected $_rowClass = 'Category_Row';
    protected $_referenceMap    = array(
        'Parent' => array(
            'columns'       => array('parent_id'),
            'refTableClass' => 'Category',
            'refColumns'    => array('id')
        )
    );
}