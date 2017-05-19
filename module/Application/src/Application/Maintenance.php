<?php

namespace Application;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Session\SessionManager;

use Autowp\Cron;
use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable\Picture;

use Exception;

use Zend_ProgressBar;
use Zend_ProgressBar_Adapter_Console;

class Maintenance extends AbstractListenerAggregate
{
    /**
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

        $comments = $serviceManager->get(Comments::class);
        /* $comments->cleanBrokenMessages();
        $comments->service()->cleanupDeleted();
        $comments->service()->cleanTopics();*/

        $imageStorage = $serviceManager->get(\Autowp\Image\Storage::class);
        $this->clearPicturesQueue($comments->service(), $imageStorage);

        $sessionManager = $serviceManager->get(\Zend\Session\SessionManager::class);
        $this->clearSessions($sessionManager);

        $userTable = new User();
        $userTable->updateSpecsVolumes();

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

    private function clearPicturesQueue(\Autowp\Comments\CommentsService $comments, \Autowp\Image\Storage $imageStorage)
    {
        $table = new Picture();
        $pictures = $table->fetchAll(
            $table->select(true)
                ->where('status = ?', Picture::STATUS_REMOVING)
                ->where('removing_date is null OR (removing_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY) )')
                ->limit(1000)
        );

        $count = count($pictures);

        if ($count) {
            print sprintf("Removing %d pictures\n", $count);

            $adapter = new Zend_ProgressBar_Adapter_Console([
                'textWidth' => 80,
                'elements'  => [
                    Zend_ProgressBar_Adapter_Console::ELEMENT_PERCENT,
                    Zend_ProgressBar_Adapter_Console::ELEMENT_BAR,
                    Zend_ProgressBar_Adapter_Console::ELEMENT_ETA,
                    Zend_ProgressBar_Adapter_Console::ELEMENT_TEXT
                ]
            ]);
            $progressBar = new Zend_ProgressBar($adapter, 0, count($pictures));

            foreach ($pictures as $idx => $picture) {
                $comments->deleteTopic(
                    \Application\Comments::PICTURES_TYPE_ID,
                    $picture->id
                );

                $imageId = $picture->image_id;
                if ($imageId) {
                    $picture->delete();
                    $imageStorage->removeImage($imageId);
                } else {
                    print "Brokern image `{$picture->id}`. Skip\n";
                }

                $progressBar->update($idx + 1, $picture->id);
            }

            $progressBar->finish();
        } else {
            print "Nothing to clear\n";
        }
    }
}
