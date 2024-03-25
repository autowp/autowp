<?php

namespace Application;

use Application\Service\PictureService;
use Autowp\Cron;
use Exception;
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
     * @throws Exception
     */
    public function dailyMaintenance(Cron\CronEvent $event): void
    {
        print "Daily maintenance\n";

        $application    = $event->getApplication();
        $serviceManager = $application->getServiceManager();

        /** @var PictureService $pictureService */
        $pictureService = $serviceManager->get(PictureService::class);
        $pictureService->clearQueue();

        /** @var Service\UsersService $usersService */
        $usersService = $serviceManager->get(Service\UsersService::class);
        $usersService->deleteUnused();

        print "Daily maintenance done\n";
    }
}
