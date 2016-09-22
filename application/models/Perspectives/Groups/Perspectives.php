<?php

class Perspectives_Groups_Perspectives extends Zend_Db_Table
{
    protected $_name = 'perspectives_groups_perspectives';
    protected $_primary = ['group_id', 'perspective_id'];
    protected $_referenceMap = [
        'Group' => [
            'columns'       => ['group_id'],
            'refTableClass' => 'Perspective_Group',
            'refColumns'    => ['id']
        ],
        'Perspective' => [
            'columns'       => ['perspective_id'],
            'refTableClass' => 'Perspectives',
            'refColumns'    => ['id']
        ]
    ];
}