<?php

namespace Autowp\User;

use Autowp\Cron;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;

use function sprintf;

 //TODO: extract to zf-components

class Maintenance extends AbstractListenerAggregate
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param int $priority
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
        $application    = $event->getApplication();
        $serviceManager = $application->getServiceManager();

        $userPasswordRemind = $serviceManager->get(Model\UserPasswordRemind::class);
        $count              = $userPasswordRemind->garbageCollect();
        print sprintf("%d password remind rows was deleted\ndone\n", $count);

        $userRename = $serviceManager->get(Model\UserRename::class);
        $count      = $userRename->garbageCollect();
        print sprintf("%d user rename rows was deleted\ndone\n", $count);
    }
}
