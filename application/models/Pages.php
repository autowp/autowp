<?php

class Pages extends Zend_Db_Table
{
    protected $_name = 'pages';

    protected $_referenceMap = array(
        'Parent' => array(
            'columns'       => array('parent_id'),
            'refTableClass' => 'Pages',
            'refColumns'    => array('id')
        )
    );
}