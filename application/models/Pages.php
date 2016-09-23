<?php

class Pages extends Zend_Db_Table
{
    const MAX_NAME = 120;
    const MAX_TITLE = 120;
    const MAX_BREADCRUMBS = 80;
    const MAX_URL = 120;
    const MAX_CLASS = 30;

    protected $_name = 'pages';

    protected $_referenceMap = array(
        'Parent' => array(
            'columns'       => array('parent_id'),
            'refTableClass' => 'Pages',
            'refColumns'    => array('id')
        )
    );
}