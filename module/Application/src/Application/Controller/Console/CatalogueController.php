<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\HostManager;
use Application\Model\BrandVehicle;
use Application\Model\DbTable\Picture;
use Application\Model\Message;
use Application\Model\PictureItem;
use Application\Service\SpecificationsService;
use Application\Service\TelegramService;

use Autowp\User\Auth\Adapter\Id as IdAuthAdapter;
use Autowp\User\Model\DbTable\User;

use Zend\Authentication\AuthenticationService;

class CatalogueController extends AbstractActionController
{
    /**
     * @var BrandVehicle
     */
    private $brandVehicle;

    /**
     * @var PictureItem
     */
    private $pictureItem;

    public function __construct(
        BrandVehicle $brandVehicle,
        PictureItem $pictureItem,
        SpecificationsService $specService,
        HostManager $hostManager,
        TelegramService $telegram,
        Message $message
    ) {
        $this->brandVehicle = $brandVehicle;
        $this->pictureItem = $pictureItem;
        $this->specService = $specService;
        $this->hostManager = $hostManager;
        $this->telegram = $telegram;
        $this->message = $message;
    }

    public function refreshBrandVehicleAction()
    {
        $this->brandVehicle->refreshAllAuto();

        Console::getInstance()->writeLine("done");
    }

    public function migrateEnginesAction()
    {
        $pictureTable = new Picture();
        
        $rows = $pictureTable->fetchAll([
            'type = ?'     => Picture::UNSORTED_TYPE_ID,
            'status = ?'   => Picture::STATUS_INBOX,
            'add_date < DATE_SUB(NOW(), INTERVAL 2 YEAR)'
        ], 'id');
        
        $userId = 9;
        
        $adapter = new IdAuthAdapter();
        $adapter->setIdentity($userId);
        
        $auth = new AuthenticationService();
        $result = $auth->authenticate($adapter);
        
        if (!$result->isValid()) {
            var_dump('fail 1'); exit;
        }
        
        if (! $auth->hasIdentity()) {
            var_dump('fail 2'); exit;
        }
        
        foreach ($rows as $picture) {
            
            print $picture->id . PHP_EOL;
            
            $previousStatusUserId = $picture->change_status_user_id;
            
            $success = $pictureTable->accept($this->pictureItem, $picture->id, $userId, $isFirstTimeAccepted);
            if ($success && $isFirstTimeAccepted) {
                $owner = $picture->findParentRow(User::class, 'Owner');
                if ($owner && ($owner->id != $userId)) {
                    $uri = $this->hostManager->getUriByLanguage($owner->language);
                    
                    if (!$uri) {
                        $uri = \Zend\Uri\UriFactory::factory('https://www.autowp.ru');
                    }
                    
                    $message = sprintf(
                        $this->translate('pm/your-picture-accepted-%s', 'default', $owner->language),
                        $this->pic()->url($picture->id, $picture->identity, true, $uri)
                    );
            
                    $this->message->send(null, $owner->id, $message);
                }
            
                $this->telegram->notifyPicture($picture->id);
            }
            
            if ($previousStatusUserId != $userId) {
                $userTable = new User();
                foreach ($userTable->find($previousStatusUserId) as $prevUser) {
                    $message = sprintf(
                        'Принята картинка %s',
                        $this->pic()->url($picture->id, $picture->identity, true)
                    );
                    $this->message->send(null, $prevUser->id, $message);
                }
            }
            
            $this->log(sprintf(
                'Картинка %s принята',
                htmlspecialchars($this->pic()->name($picture, $this->language()))
            ), $picture);
            
            sleep(20);
        }

        Console::getInstance()->writeLine("done");
    }
}
