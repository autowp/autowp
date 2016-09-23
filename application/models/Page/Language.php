<?php

use Application\Db\Table;

class Page_Language extends Table
{
    protected $_name = 'page_language';

    protected $_referenceMap = [
        'Page' => [
            'columns'       => ['page_id'],
            'refTableClass' => 'Pages',
            'refColumns'    => ['id']
        ]
    ];
}