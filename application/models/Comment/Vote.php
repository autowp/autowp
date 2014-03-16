<?php

class Comment_Vote extends Project_Db_Table
{
    protected $_name = 'comment_vote';
    protected $_primary = array('user_id', 'comment_id');
    protected $_referenceMap = array(
        'User' => array(
            'columns'       => array('user_id'),
            'refTableClass' => 'Users',
            'refColumns'    => array('id')
        ),
        'Comment' => array(
            'columns'       => array('comment_id'),
            'refTableClass' => 'Comment_Message',
            'refColumns'    => array('id')
        ),
    );
}