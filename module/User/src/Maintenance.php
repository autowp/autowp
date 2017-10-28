<?php

namespace Autowp\User;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;

use Autowp\Cron;

 //TODO: extract to zf-components

class Maintenance extends AbstractListenerAggregate
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param EventManagerInterface $events
     * @param int                   $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(Cron\CronEvent::EVENT_DAILY_MAINTENANCE, [$this, 'dailyMaintenance']);
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function dailyMaintenance(Cron\CronEvent $event)
    {
        $application = $event->getApplication();
        $serviceManager = $application->getServiceManager();

        $userRemember = $serviceManager->get(\Autowp\User\Model\UserRemember::class);
        $count = $userRemember->garbageCollect();
        print sprintf("%d user remember rows was deleted\ndone\n", $count);

        $userPasswordRemind = $serviceManager->get(\Autowp\User\Model\UserPasswordRemind::class);
        $count = $userPasswordRemind->garbageCollect();
        print sprintf("%d password remind rows was deleted\ndone\n", $count);

        $userRename = $serviceManager->get(\Autowp\User\Model\UserRename::class);
        $count = $userRename->garbageCollect();
        print sprintf("%d user rename rows was deleted\ndone\n", $count);
    }
}
