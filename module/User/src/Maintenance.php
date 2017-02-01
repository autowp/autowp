<?php

namespace Autowp\User;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;

use Autowp\User\Model\DbTable\User\PasswordRemind as UserPasswordRemind;
use Autowp\User\Model\DbTable\User\Remember as UserRemember;
use Autowp\User\Model\DbTable\User\Rename as UserRename;

use Application\CronEvent; //TODO: extract to zf-components

class Maintenance extends AbstractListenerAggregate
{
    /**
     * @param EventManagerInterface $events
     * @param int                   $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(CronEvent::EVENT_DAILY_MAINTENANCE, [$this, 'dailyMaintenance']);
    }
    
    public function dailyMaintenance(CronEvent $event)
    {
        $this->clearUserRemember();
        $this->clearUserPasswordRemind();
        $this->clearUserRenames();
    }
    
    private function clearUserRemember()
    {
        $urTable = new UserRemember();
        $count = $urTable->delete([
            'date < DATE_SUB(NOW(), INTERVAL 60 DAY)'
        ]);
    
        print sprintf("%d user remember rows was deleted\ndone\n", $count);
    }
    
    private function clearUserPasswordRemind()
    {
        $uprTable = new UserPasswordRemind();
        $count = $uprTable->delete([
            'created < DATE_SUB(NOW(), INTERVAL 10 DAY)'
        ]);
    
        print sprintf("%d password remind rows was deleted\ndone\n", $count);
    }
    
    private function clearUserRenames()
    {
        $urTable = new UserRename();
        $count = $urTable->delete([
            'date < DATE_SUB(NOW(), INTERVAL 3 MONTH)'
        ]);
    
        print sprintf("%d user rename rows was deleted\ndone\n", $count);
    }
}