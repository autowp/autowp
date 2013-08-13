<?php

class Perspectives_Groups extends Zend_Db_Table
{
    protected $_name = 'perspectives_groups';
    protected $_referenceMap = array(
        'Page' => array(
            'columns'       => array('page_id'),
            'refTableClass' => 'Perspectives_Pages',
            'refColumns'    => array('id')
        )
    );
}