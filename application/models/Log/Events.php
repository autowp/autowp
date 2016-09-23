<?php

use Application\Db\Table;

class Log_Events extends Table
{
    protected $_name = 'log_events';
    protected $_rowClass = 'Log_Events_Row';
    protected $_referenceMap = [
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => 'Users',
            'refColumns'    => ['id']
        ]
    ];

    public function insert(array $data)
    {
        $data['add_datetime'] = new Zend_Db_Expr('NOW()');

        return parent::insert($data);
    }

    public function __invoke($userId, $message, $objects)
    {
        $event = $this->createRow([
            'description' => $message,
            'user_id'     => (int)$userId
        ]);
        $event->save();
        foreach (is_array($objects) ? $objects : [$objects] as $object) {
            $event->assign($object);
        }
    }
}