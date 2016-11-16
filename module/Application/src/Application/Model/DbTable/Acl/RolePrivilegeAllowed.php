<?php

namespace Application\Model\DbTable\Acl;

use Zend_Db_Table;

class RolePrivilegeAllowed extends Zend_Db_Table
{
    protected $_primary = ['role_id', 'privilege_id'];
    protected $_name = 'acl_roles_privileges_allowed';

    protected $_referenceMap = [
        'Role' => [
            'columns'       => ['role_id'],
            'refTableClass' => Role::class,
            'refColumns'    => ['id']
        ],
        'Privilege' => [
            'columns'       => ['privilege_id'],
            'refTableClass' => ResourcePrivilege::class,
            'refColumns'    => ['id']
        ]
    ];
}
