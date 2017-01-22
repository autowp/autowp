<?php

namespace Application\Controller\Console;

use Zend\Mvc\Controller\AbstractActionController;

use Application\DuplicateFinder;
use Application\HostManager;
use Application\Model\BrandVehicle;
use Application\Model\DbTable;
use Application\Model\PictureItem;
use Application\Service\SpecificationsService;
use Application\Service\TelegramService;

use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;
use Autowp\Message\MessageService;
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

    private $textStorage;

    /**
     * @var MessageService
     */
    private $message;

    public function __construct(
        BrandVehicle $brandVehicle,
        PictureItem $pictureItem,
        SpecificationsService $specService,
        HostManager $hostManager,
        TelegramService $telegram,
        MessageService $message,
        $textStorage,
        DuplicateFinder $duplicateFinder
    ) {
        $this->brandVehicle = $brandVehicle;
        $this->pictureItem = $pictureItem;
        $this->specService = $specService;
        $this->hostManager = $hostManager;
        $this->telegram = $telegram;
        $this->message = $message;
        $this->textStorage = $textStorage;
        $this->duplicateFinder = $duplicateFinder;
    }

    public function refreshBrandVehicleAction()
    {
        $this->brandVehicle->refreshAllAuto();

        return "done\n";
    }

    public function acceptOldUnsortedAction()
    {
        $pictureTable = new DbTable\Picture();

        $rows = $pictureTable->fetchAll([
            'type = ?'     => DbTable\Picture::UNSORTED_TYPE_ID,
            'status = ?'   => DbTable\Picture::STATUS_INBOX,
            'add_date < DATE_SUB(NOW(), INTERVAL 2 YEAR)'
        ], 'id');

        $userId = 9;

        $adapter = new IdAuthAdapter();
        $adapter->setIdentity($userId);

        $auth = new AuthenticationService();
        $result = $auth->authenticate($adapter);

        if (! $result->isValid()) {
            var_dump('fail 1');
            exit;
        }

        if (! $auth->hasIdentity()) {
            var_dump('fail 2');
            exit;
        }

        foreach ($rows as $picture) {
            print $picture->id . PHP_EOL;

            $previousStatusUserId = $picture->change_status_user_id;

            $success = $pictureTable->accept($picture->id, $userId, $isFirstTimeAccepted);
            if ($success && $isFirstTimeAccepted) {
                $owner = $picture->findParentRow(User::class, 'Owner');
                if ($owner && ($owner->id != $userId)) {
                    $uri = $this->hostManager->getUriByLanguage($owner->language);

                    if (! $uri) {
                        $uri = \Zend\Uri\UriFactory::factory('https://www.autowp.ru');
                    }

                    $message = sprintf(
                        $this->translate('pm/your-picture-accepted-%s', 'default', $owner->language),
                        $this->pic()->url($picture->identity, true, $uri)
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
                        $this->pic()->url($picture->identity, true)
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

        return "done\n";
    }

    public function rebuildCarOrderCacheAction()
    {
        $itemTable = new DbTable\Item();

        $select = $itemTable->select(true)
            ->order('id');

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );
        $paginator->setItemCountPerPage(100);

        $pagesCount = $paginator->count();
        for ($i = 1; $i <= $pagesCount; $i++) {
            $paginator->setCurrentPageNumber($i);
            foreach ($paginator->getCurrentItems() as $carRow) {
                print $carRow->id . "\n";
                $carRow->updateOrderCache();
            }
        }

        return "ok\n";
    }
}
