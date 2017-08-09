<?php

namespace Application\Controller\Console;

use Zend\Mvc\Controller\AbstractActionController;

use Application\DuplicateFinder;
use Application\HostManager;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Service\SpecificationsService;
use Application\Service\TelegramService;

use Autowp\Message\MessageService;
use Autowp\User\Auth\Adapter\Id as IdAuthAdapter;
use Autowp\User\Model\DbTable\User;

use Zend\Authentication\AuthenticationService;

class CatalogueController extends AbstractActionController
{
    /**
     * @var ItemParent
     */
    private $itemParent;

    /**
     * @var PictureItem
     */
    private $pictureItem;

    private $textStorage;

    /**
     * @var MessageService
     */
    private $message;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var User
     */
    private $userTable;

    public function __construct(
        ItemParent $itemParent,
        PictureItem $pictureItem,
        SpecificationsService $specService,
        HostManager $hostManager,
        TelegramService $telegram,
        MessageService $message,
        $textStorage,
        DuplicateFinder $duplicateFinder,
        Item $itemModel,
        Picture $picture
    ) {
        $this->itemParent = $itemParent;
        $this->pictureItem = $pictureItem;
        $this->specService = $specService;
        $this->hostManager = $hostManager;
        $this->telegram = $telegram;
        $this->message = $message;
        $this->textStorage = $textStorage;
        $this->duplicateFinder = $duplicateFinder;
        $this->itemModel = $itemModel;
        $this->picture = $picture;
        $this->userTable = new User();
    }

    public function refreshBrandVehicleAction()
    {
        $this->itemParent->refreshAllAuto();

        return "done\n";
    }

    public function acceptOldUnsortedAction()
    {
        $select = $this->picture->getTable()->getSql()->select();
        $select
            ->where([
                'type = ?'   => Picture::UNSORTED_TYPE_ID,
                'status = ?' => Picture::STATUS_INBOX,
                'add_date < DATE_SUB(NOW(), INTERVAL 2 YEAR)'
            ])
            ->order('id');

        $rows = $this->picture->getTable()->selectWith($select);

        $userId = 9;

        $adapter = new IdAuthAdapter();
        $adapter->setIdentity($userId);

        $auth = new AuthenticationService();
        $result = $auth->authenticate($adapter);

        if (! $result->isValid()) {
            var_dump('fail 1');
            return;
        }

        if (! $auth->hasIdentity()) {
            var_dump('fail 2');
            return;
        }

        foreach ($rows as $picture) {
            print $picture['id']. PHP_EOL;

            $previousStatusUserId = $picture['change_status_user_id'];

            $success = $this->picture->accept($picture['id'], $userId, $isFirstTimeAccepted);
            if ($success && $isFirstTimeAccepted) {
                $owner = $this->userTable->find((int)$picture['owner_id'])->current();
                if ($owner && ($owner['id'] != $userId)) {
                    $uri = $this->hostManager->getUriByLanguage($owner['language']);

                    if (! $uri) {
                        $uri = \Zend\Uri\UriFactory::factory('https://www.autowp.ru');
                    }

                    $message = sprintf(
                        $this->translate('pm/your-picture-accepted-%s', 'default', $owner['language']),
                        $this->pic()->url($picture['identity'], true, $uri)
                    );

                    $this->message->send(null, $owner['id'], $message);
                }

                $this->telegram->notifyPicture($picture['id']);
            }

            if ($previousStatusUserId != $userId) {
                foreach ($this->userTable->find($previousStatusUserId) as $prevUser) {
                    $message = sprintf(
                        'Принята картинка %s',
                        $this->pic()->url($picture['identity'], true)
                    );
                    $this->message->send(null, $prevUser['id'], $message);
                }
            }

            $this->log(sprintf(
                'Картинка %s принята',
                htmlspecialchars($this->pic()->name($picture, $this->language()))
            ), [
                'pictures' => $picture['id']
            ]);

            sleep(20);
        }

        return "done\n";
    }

    public function rebuildCarOrderCacheAction()
    {
        $paginator = $this->itemModel->getPaginator([
            'columns' => ['id']
        ]);
        $paginator->setItemCountPerPage(100);

        $pagesCount = $paginator->count();
        for ($i = 1; $i <= $pagesCount; $i++) {
            $paginator->setCurrentPageNumber($i);
            foreach ($paginator->getCurrentItems() as $item) {
                print $item['id'] . "\n";
                $this->itemModel->updateOrderCache($item['id']);
            }
        }

        return "ok\n";
    }
}
