<?php

namespace Application\Model\DbTable;

use Zend_Db_Table;

class Page extends Zend_Db_Table
{
    const MAX_NAME = 120;
    const MAX_TITLE = 120;
    const MAX_BREADCRUMBS = 80;
    const MAX_URL = 120;
    const MAX_CLASS = 30;

    protected $_name = 'pages';

    protected $_referenceMap = [
        'Parent' => [
            'columns'       => ['parent_id'],
            'refTableClass' => self::class,
            'refColumns'    => ['id']
        ]
    ];
}