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
        $this->listeners[] = $events->attach(Cron\CronEvent::EVENT_MIDNIGHT, [$this, 'midnight']);
    }

    /**
     * @throws Exception
     */
    public function dailyMaintenance(Cron\CronEvent $event): void
    {
        print "Daily maintenance\n";

        $application    = $event->getApplication();
        $serviceManager = $application->getServiceManager();

        /* $comments = $serviceManager->get(Comments::class);
        $comments->cleanBrokenMessages();
        $comments->service()->cleanupDeleted();
        $comments->service()->cleanTopics();*/

        /** @var PictureService $pictureService */
        $pictureService = $serviceManager->get(PictureService::class);
        $pictureService->clearQueue();

        /** @var Service\UsersService $usersService */
        $usersService = $serviceManager->get(Service\UsersService::class);
        $usersService->deleteUnused();

        print "Daily maintenance done\n";
    }

    /**
     * @throws Exception
     */
    public function midnight(Cron\CronEvent $event): void
    {
        print "Midnight\n";

        $application    = $event->getApplication();
        $serviceManager = $application->getServiceManager();

        /** @var Model\CarOfDay $carOfDay */
        $carOfDay = $serviceManager->get(Model\CarOfDay::class);
        $carOfDay->pick();

        $twitterConfig = $serviceManager->get('Config')['twitter'];
        $carOfDay->putCurrentToTwitter($twitterConfig);

        $facebookConfig = $serviceManager->get('Config')['facebook'];
        $carOfDay->putCurrentToFacebook($facebookConfig);

        $vkConfig = $serviceManager->get('Config')['vk'];
        $carOfDay->putCurrentToVk($vkConfig);

        print "Midnight done\n";
    }
}
