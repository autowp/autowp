<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Log_Events;

class Log extends AbstractPlugin
{
    public function __invoke($message, $objects)
    {
        $user = $this->getController()->user()->get();

        $table = new Log_Events();

        $event = $table->createRow([
            'description' => $message,
            'user_id'     => $user ? $user->id : null
        ]);
        $event->save();
        foreach (is_array($objects) ? $objects : [$objects] as $object) {
            $event->assign($object);
        }
    }
}