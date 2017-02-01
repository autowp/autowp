<?php

namespace Application\Controller\Console;

use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\ApplicationInterface;
use Zend\Mvc\Controller\AbstractActionController;

use Application\CronEvent;

class CronController extends AbstractActionController
{
    /**
     * @var EventManagerInterface
     */
    private $eventManager;
    
    /**
     * @var ApplicationInterface
     */
    private $application;
    
    public function __construct(
        ApplicationInterface $application, 
        EventManagerInterface $comments
    ) {
        $this->application = $application;
        $this->eventManager = $comments;
    }
    
    public function dailyMaintenanceAction()
    {
        $event = new CronEvent(CronEvent::EVENT_DAILY_MAINTENANCE);
        $event->setApplication($this->application);
        $this->eventManager->triggerEvent($event);
    }
    
    public function midnightAction()
    {
        $event = new CronEvent(CronEvent::EVENT_MIDNIGHT);
        $event->setApplication($this->application);
        $this->eventManager->triggerEvent($event);
    }
}
