<?php

class Perspective_Group extends Zend_Db_Table
{
    protected $_name = 'perspectives_groups';
    protected $_referenceMap = [
        'Page' => [
            'columns'       => ['page_id'],
            'refTableClass' => 'Perspective_Page',
            'refColumns'    => ['id']
        ]
    ];
}