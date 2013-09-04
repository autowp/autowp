<?php

class User_Account extends Project_Db_Table
{
    protected $_name = 'user_account';

    protected $_referenceMap = array(
        'User' => array(
            'columns'       => array('user_id'),
            'refTableClass' => 'Users',
            'refColumns'    => array('id')
        ),
    );
}