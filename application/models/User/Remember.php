<?php

class User_Remember extends Project_Db_Table
{
    protected $_name = 'user_remember';

    protected $_primary = array('token');

    protected $_referenceMap = array(
        'User' => array(
            'columns'       => array('user_id'),
            'refTableClass' => 'Users',
            'refColumns'    => array('id')
        )
    );

}