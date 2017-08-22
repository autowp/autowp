<?php

namespace Application;

use Exception;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Session\SessionManager;

use Autowp\Cron;

use Application\Service\PictureService;

class Maintenance extends AbstractListenerAggregate
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param EventManagerInterface $events
     * @param int                   $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(Cron\CronEvent::EVENT_DAILY_MAINTENANCE, [$this, 'dailyMaintenance']);
        $this->listeners[] = $events->attach(Cron\CronEvent::EVENT_MIDNIGHT, [$this, 'midnight']);
    }

    public function dailyMaintenance(Cron\CronEvent $event)
    {
        print "Daily maintenance\n";

        $application = $event->getApplication();
        $serviceManager = $application->getServiceManager();

        /* $comments = $serviceManager->get(Comments::class);
        $comments->cleanBrokenMessages();
        $comments->service()->cleanupDeleted();
        $comments->service()->cleanTopics();*/

        $pictureService = $serviceManager->get(PictureService::class);
        $pictureService->clearQueue();

        $sessionManager = $serviceManager->get(\Zend\Session\SessionManager::class);
        $this->clearSessions($sessionManager);

        $userModel = $serviceManager->get(\Autowp\User\Model\User::class);
        $userModel->updateSpecsVolumes();

        $usersService = $serviceManager->get(Service\UsersService::class);
        $usersService->deleteUnused();

        print "Daily maintenance done\n";
    }

    public function midnight(Cron\CronEvent $event)
    {
        print "Midnight\n";

        $application = $event->getApplication();
        $serviceManager = $application->getServiceManager();

        $carOfDay = $serviceManager->get(Model\CarOfDay::class);
        $carOfDay->pick();

        $twitterConfig = $serviceManager->get('Config')['twitter'];
        $carOfDay->putCurrentToTwitter($twitterConfig);

        $facebookConfig = $serviceManager->get('Config')['facebook'];
        $carOfDay->putCurrentToFacebook($facebookConfig);

        $usersService = $serviceManager->get(Service\UsersService::class);

        $usersService->restoreVotes();
        print "User votes restored\n";

        $affected = $usersService->updateUsersVoteLimits();
        print sprintf("Updated %s users vote limits\n", $affected);

        $vkConfig = $serviceManager->get('Config')['vk'];
        $carOfDay->putCurrentToVk($vkConfig);

        print "Midnight done\n";
    }

    private function clearSessions($sessionManager)
    {
        $gcMaxLifetime = $sessionManager->getConfig()->getOptions('options')['gc_maxlifetime'];
        if (! $gcMaxLifetime) {
            throw new Exception('Option session.gc_maxlifetime not found');
        }

        $sessionManager->getSaveHandler()->gc($gcMaxLifetime);

        return "Garabage collected\n";
    }
}
