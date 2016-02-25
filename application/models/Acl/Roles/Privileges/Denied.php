<?php

class Acl_Roles_Privileges_Denied extends Zend_Db_Table
{
    protected $_primary = array('role_id', 'privilege_id');
    protected $_name = 'acl_roles_privileges_denied';
    
    protected $_referenceMap    = array(
        'Role' => array(
            'columns'           => array('role_id'),
            'refTableClass'     => 'Acl_Roles',
            'refColumns'        => array('id')
        ),
        'Privilege' => array(
            'columns'           => array('privilege_id'),
            'refTableClass'     => 'Acl_Resources_Privileges',
            'refColumns'        => array('id')
        )
    );
}