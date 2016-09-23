<?php

class Acl_Roles_Parents extends Zend_Db_Table
{
    protected $_primary = array('role_id', 'parent_role_id');
    protected $_name = 'acl_roles_parents';
    
    protected $_referenceMap    = array(
        'Role' => array(
            'columns'           => array('role_id'),
            'refTableClass'     => 'Acl_Roles',
            'refColumns'        => array('id')
        ),
        'Parent_Role' => array(
            'columns'           => array('parent_role_id'),
            'refTableClass'     => 'Acl_Roles',
            'refColumns'        => array('id')
        ),
    );
}