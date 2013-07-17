<?php

class Project_Controller_Action_Helper_Log extends Zend_Controller_Action_Helper_Abstract
{
    private $_table;

    public function __construct()
    {
        $this->_table = new Log_Events();
    }

    public function direct($message, $objects)
    {
        $user = $this->getActionController()
            ->getHelper('user')->direct()->get();

        $event = $this->_table->createRow(array(
            'description' => $message,
            'user_id'     => $user ? $user->id : null
        ));
        $event->save();
        foreach (is_array($objects) ? $objects : array($objects) as $object) {
            $event->assign($object);
        }
    }
}