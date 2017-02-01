<?php

namespace Application;

use Zend\EventManager\Event;
use Zend\Mvc\ApplicationInterface;

class CronEvent extends Event
{
    const EVENT_DAILY_MAINTENANCE = 'daily.maintenance';
    const EVENT_MIDNIGHT = 'midnight';
    
    protected $application;
    
    /**
     * Set application instance
     *
     * @param  ApplicationInterface $application
     * @return MvcEvent
     */
    public function setApplication(ApplicationInterface $application)
    {
        $this->setParam('application', $application);
        $this->application = $application;
        return $this;
    }
    
    /**
     * Get application instance
     *
     * @return ApplicationInterface
     */
    public function getApplication()
    {
        return $this->application;
    }
}