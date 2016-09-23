<?php

class Acl_Resources_Privileges extends Zend_Db_Table
{
    protected $_primary = array('resource_id', 'name');
    protected $_name = 'acl_resources_privileges';
    
    protected $_referenceMap    = array(
        'Resource' => array(
            'columns'           => array('resource_id'),
            'refTableClass'     => 'Acl_Resources',
            'refColumns'        => array('id')
        )
    );
}