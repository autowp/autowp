<?php

use Application\Db\Table;

class Picture_Moder_Vote extends Table
{
    protected $_name = 'pictures_moder_votes';
    protected $_primary = ['picture_id', 'user_id'];

    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => \Application\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ],
        'Picture' => [
            'columns'       => ['picture_id'],
            'refTableClass' => 'Picture',
            'refColumns'    => ['id']
        ]
    ];
}