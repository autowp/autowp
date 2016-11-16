<?php

namespace Application\Model\DbTable\Acl;

use Zend_Db_Table;

class ResourcePrivilege extends Zend_Db_Table
{
    protected $_primary = ['resource_id', 'name'];
    protected $_name = 'acl_resources_privileges';

    protected $_referenceMap = [
        'Resource' => [
            'columns'       => ['resource_id'],
            'refTableClass' => Resource::class,
            'refColumns'    => ['id']
        ]
    ];
}
