<?php

class Comment_Topic_View extends Project_Db_Table
{
    protected $_name = 'comment_topic_view';
    protected $_primary = array('type_id', 'item_id', 'user_id');
    protected $_referenceMap = array(
        'User' => array(
            'columns'       => array('user_id'),
            'refTableClass' => 'Users',
            'refColumns'    => array('id')
        ),
    );
}