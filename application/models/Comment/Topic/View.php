<?php

use Application\Db\Table;

class Comment_Topic_View extends Table
{
    protected $_name = 'comment_topic_view';
    protected $_primary = ['type_id', 'item_id', 'user_id'];
    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => \Application\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ],
    ];
}