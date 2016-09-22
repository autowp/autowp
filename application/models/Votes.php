<?php

class Votes extends Project_Db_Table
{
    protected $_name = 'votes';
    protected $_primary = ['picture_id', 'day_date'];
    protected $_referenceMap = [
        'Picture' => [
            'columns'       => ['picture_id'],
            'refTableClass' => 'Picture',
            'refColumns'    => ['id']
        ]
    ];
}