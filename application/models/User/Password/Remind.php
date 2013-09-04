<?php

class User_Password_Remind extends Project_Db_Table
{
    protected $_name = 'user_password_remind';
    protected $_primary = 'hash';

    protected $_referenceMap = array(
        'User' => array(
            'columns'       => array('user_id'),
            'refTableClass' => 'Users',
            'refColumns'    => array('id')
        ),
    );
}