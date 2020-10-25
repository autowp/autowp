<?php

namespace Autowp\Message;

use Autowp\Cron;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;

class Maintenance extends AbstractListenerAggregate
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param int $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(Cron\CronEvent::EVENT_DAILY_MAINTENANCE, [$this, 'dailyMaintenance']);
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function dailyMaintenance(Cron\CronEvent $event): void
    {
        /*$application = $event->getApplication();
        $serviceManager = $application->getServiceManager();

        $service = $serviceManager->get(MessageService::class);*/

        /*$count = $service->recycleSystem();
        print sprintf("%d messages was deleted\n", $count);*/

        /*$count = $service->recycle();
        print sprintf("%d messages was deleted\n", $count);*/
    }
}
