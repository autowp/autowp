<?php

namespace Application\Model\DbTable;

use Zend_Db_Table;

class Category extends Zend_Db_Table
{
    protected $_name = 'category';
    protected $_rowClass = \Application\Model\DbTable\Category\Row::class;
    protected $_referenceMap = [
        'Parent' => [
            'columns'       => ['parent_id'],
            'refTableClass' => self::class,
            'refColumns'    => ['id']
        ]
    ];
}