<?php

namespace Application\Model\DbTable\Perspective;

use Zend_Db_Table;

class GroupPerspective extends Zend_Db_Table
{
    protected $_name = 'perspectives_groups_perspectives';
    protected $_primary = ['group_id', 'perspective_id'];
    protected $_referenceMap = [
        'Group' => [
            'columns'       => ['group_id'],
            'refTableClass' => \Application\Model\DbTable\Perspective\Group::class,
            'refColumns'    => ['id']
        ],
        'Perspective' => [
            'columns'       => ['perspective_id'],
            'refTableClass' => \Application\Model\DbTable\Perspective::class,
            'refColumns'    => ['id']
        ]
    ];
}