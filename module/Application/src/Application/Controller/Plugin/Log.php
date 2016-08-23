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
        $table($user->id, $message, $objects);
    }
}
