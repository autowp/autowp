<?php

namespace Autowp\Message;

use Autowp\Cron;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;

class Maintenance extends AbstractListenerAggregate
{
    /**
     * @param EventManagerInterface $events
     * @param int                   $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(Cron\CronEvent::EVENT_DAILY_MAINTENANCE, [$this, 'dailyMaintenance']);
    }
    
    public function dailyMaintenance(Cron\CronEvent $event)
    {
        $application = $event->getApplication();
        $serviceManager = $application->getServiceManager();
        
        $service = $serviceManager->get(MessageService::class);
        
        /*$count = $service->recycleSystem();
        print sprintf("%d messages was deleted\n", $count);*/
        
        /*$count = $service->recycle();
        print sprintf("%d messages was deleted\n", $count);*/
    }
}