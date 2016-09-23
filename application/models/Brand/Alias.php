<?php

class Brand_Alias extends Zend_Db_Table
{
    protected $_name = 'brand_alias';
    protected $_primary = ['name'];

    protected $_referenceMap    = [
        'Brand' => [
            'columns'       => ['brand_id'],
            'refTableClass' => 'Brands',
            'refColumns'    => ['id']
        ],
    ];
}