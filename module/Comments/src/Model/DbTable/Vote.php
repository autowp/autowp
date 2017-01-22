<?php

namespace Autowp\Comments\Model\DbTable;

use Autowp\Commons\Db\Table;

class Vote extends Table
{
    protected $_name = 'comment_vote';
    protected $_primary = ['user_id', 'comment_id'];
    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => \Autowp\User\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ],
        'Comment' => [
            'columns'       => ['comment_id'],
            'refTableClass' => Message::class,
            'refColumns'    => ['id']
        ],
    ];
}
