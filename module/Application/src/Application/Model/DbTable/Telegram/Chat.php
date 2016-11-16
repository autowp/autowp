<?php

namespace Application\Model\DbTable\Telegram;

use Zend_Db_Table;

class Chat extends Zend_Db_Table
{
    protected $_name = 'telegram_chat';
    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => \Application\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ],
    ];
}
