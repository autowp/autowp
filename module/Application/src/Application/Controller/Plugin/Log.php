<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Application\Model\DbTable\Log\Event as LogEvent;

class Log extends AbstractPlugin
{
    public function __invoke($message, $objects)
    {
        $user = $this->getController()->user()->get();
        $table = new LogEvent();
        $table($user->id, $message, $objects);
    }
}
