<?php

class Log_Events extends Project_Db_Table
{
    protected $_name = 'log_events';
    protected $_rowClass = 'Log_Events_Row';
    protected $_referenceMap = array(
        'User' => array(
            'columns'       => array('user_id'),
            'refTableClass' => 'Users',
            'refColumns'    => array('id')
        )
    );

    public function insert(array $data)
    {
        $data['add_datetime'] = new Zend_Db_Expr('NOW()');

        return parent::insert($data);
    }
}