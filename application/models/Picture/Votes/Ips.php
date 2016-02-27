<?php

class Picture_Votes_Ips extends Zend_Db_Table
{
    protected $_name = 'picture_votes_ips';
    protected $_primary = array('picture_id', 'ip');
    protected $_referenceMap    = array(
        'Picture' => array(
            'columns'       => array('picture_id'),
            'refTableClass' => 'Picture',
            'refColumns'    => array('id')
        )
    );
}