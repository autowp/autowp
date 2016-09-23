<?php

use Application\Db\Table;

class Category_Language extends Table
{
    protected $_name = 'category_language';
    protected $_primary = ['category_id', 'language'];

    protected $_referenceMap = [
        'Category' => [
            'columns'       => ['category_id'],
            'refTableClass' => 'Category',
            'refColumns'    => ['id']
        ]
    ];
}