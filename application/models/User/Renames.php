<?php

class User_Renames extends Project_Db_Table
{
    protected $_name = 'user_renames';

    protected $_referenceMap = array(
        'User' => array(
            'columns'       => array('user_id'),
            'refTableClass' => 'Users',
            'refColumns'    => array('id')
        )
    );
}