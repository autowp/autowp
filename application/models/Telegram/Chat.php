<?php

class Telegram_Chat extends Zend_Db_Table
{
    protected $_name = 'telegram_chat';
    protected $_referenceMap    = array(
        'User' => array(
            'columns'           => array('user_id'),
            'refTableClass'     => 'Users',
            'refColumns'        => array('id')
        ),
    );
}