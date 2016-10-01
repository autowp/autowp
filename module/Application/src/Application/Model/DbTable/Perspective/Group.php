<?php

namespace Application\Model\DbTable\Perspective;

use Zend_Db_Table;

class Group extends Zend_Db_Table
{
    protected $_name = 'perspectives_groups';
    protected $_referenceMap = [
        'Page' => [
            'columns'       => ['page_id'],
            'refTableClass' => \Application\Model\DbTable\Perspective\Page::class,
            'refColumns'    => ['id']
        ]
    ];
}