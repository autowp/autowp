<?php

namespace Autowp\User;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;

use Autowp\Cron;
use Autowp\User\Model\DbTable\User\PasswordRemind as UserPasswordRemind;
use Autowp\User\Model\DbTable\User\Remember as UserRemember;

use Application\CronEvent;

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

        $this->clearUserRemember();
        $this->clearUserPasswordRemind();

        $userRename = $serviceManager->get(\Autowp\User\Model\UserRename::class);
        $count = $userRename->garbageCollect();
        print sprintf("%d user rename rows was deleted\ndone\n", $count);
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
}
