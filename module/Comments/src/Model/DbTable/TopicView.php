<?php

namespace Autowp\Comments\Model\DbTable;

use Autowp\Commons\Db\Table;

class TopicView extends Table
{
    protected $_name = 'comment_topic_view';
    protected $_primary = ['type_id', 'item_id', 'user_id'];
    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => \Autowp\User\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ],
    ];
}
