<?php

class Category_Car extends Zend_Db_Table
{
    protected $_name = 'category_car';
    protected $_primary = array('category_id', 'car_id');
    protected $_referenceMap = array(
        'Category' => array(
            'columns'       => array('category_id'),
            'refTableClass' => 'Category',
            'refColumns'    => array('id')
        ),
        'Car' => array(
            'columns'       => array('car_id'),
            'refTableClass' => 'Cars',
            'refColumns'    => array('id')
        ),
        'User' => array(
            'columns'       => array('user_id'),
            'refTableClass' => 'Users',
            'refColumns'    => array('id')
        )
    );
}