<?php

class Engines extends Project_Db_Table
{
    protected $_name = 'engines';
    protected $_primary = 'id';
    protected $_rowClass = 'Engines_Row';
    protected $_referenceMap = array(
        'Last_Editor' => array(
            'columns'       => array('last_editor_id'),
            'refTableClass' => 'Users',
            'refColumns'    => array('id')
        )
    );
}