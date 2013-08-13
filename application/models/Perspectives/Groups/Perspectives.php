<?php

class Perspectives_Groups_Perspectives extends Zend_Db_Table
{
    protected $_name = 'perspectives_groups_perspectives';
    protected $_primary = array('group_id', 'perspective_id');
    protected $_referenceMap = array(
        'Group' => array(
            'columns'       => array('group_id'),
            'refTableClass' => 'Perspectives_Groups',
            'refColumns'    => array('id')
        ),
        'Perspective' => array(
            'columns'       => array('perspective_id'),
            'refTableClass' => 'Perspectives',
            'refColumns'    => array('id')
        )
    );
}