<?php

class Pictures_Moder_Votes extends Project_Db_Table
{
    protected $_name = 'pictures_moder_votes';
    protected $_primary = array('picture_id', 'user_id');

    protected $_referenceMap    = array(
        'User' => array(
            'columns'           => array('user_id'),
            'refTableClass'     => 'Users',
            'refColumns'        => array('id')
        ),
        'Picture' => array(
            'columns'           => array('picture_id'),
            'refTableClass'     => 'Picture',
            'refColumns'        => array('id')
        )
    );
}