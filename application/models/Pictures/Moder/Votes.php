<?php

class Pictures_Moder_Votes extends Project_Db_Table
{
    protected $_name = 'pictures_moder_votes';
    protected $_primary = ['picture_id', 'user_id'];

    protected $_referenceMap    = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => 'Users',
            'refColumns'    => ['id']
        ],
        'Picture' => [
            'columns'       => ['picture_id'],
            'refTableClass' => 'Picture',
            'refColumns'    => ['id']
        ]
    ];
}