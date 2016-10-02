<?php

namespace Application\Model\DbTable\Acl;

use Zend_Db_Table;

class RoleParent extends Zend_Db_Table
{
    protected $_primary = ['role_id', 'parent_role_id'];
    protected $_name = 'acl_roles_parents';

    protected $_referenceMap = [
        'Role' => [
            'columns'       => ['role_id'],
            'refTableClass' => Role::class,
            'refColumns'    => ['id']
        ],
        'Parent_Role' => [
            'columns'       => ['parent_role_id'],
            'refTableClass' => Role::class,
            'refColumns'    => ['id']
        ],
    ];
}