<?php

class Page_Language extends Project_Db_Table
{
    protected $_name = 'page_language';

    protected $_referenceMap = array(
        'Page' => array(
            'columns'       => array('page_id'),
            'refTableClass' => 'Pages',
            'refColumns'    => array('id')
        )
    );
}