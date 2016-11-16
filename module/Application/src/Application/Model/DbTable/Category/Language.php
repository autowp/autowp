<?php

namespace Application\Model\DbTable\Category;

use Application\Db\Table;

class Language extends Table
{
    protected $_name = 'category_language';
    protected $_primary = ['category_id', 'language'];

    protected $_referenceMap = [
        'Category' => [
            'columns'       => ['category_id'],
            'refTableClass' => \Application\Model\DbTable\Category::class,
            'refColumns'    => ['id']
        ]
    ];
}
