<?php

namespace Application\Model\DbTable\Comment;

use Application\Db\Table;

class Vote extends Table
{
    protected $_name = 'comment_vote';
    protected $_primary = ['user_id', 'comment_id'];
    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => \Application\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ],
        'Comment' => [
            'columns'       => ['comment_id'],
            'refTableClass' => \Application\Model\DbTable\Comment\Message::class,
            'refColumns'    => ['id']
        ],
    ];
}
