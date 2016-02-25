<?php

class Votes extends Project_Db_Table
{
    protected $_name = 'votes';
    protected $_rowsetClass = 'Cars_Rowset';
    protected $_primary = array('picture_id', 'day_date');
    protected $_referenceMap    = array(
        'Picture' => array(
            'columns'           => array('picture_id'),
            'refTableClass'     => 'Picture',
            'refColumns'        => array('id')
        ),
    );
}