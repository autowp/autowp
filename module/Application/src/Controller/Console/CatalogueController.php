<?php

namespace Application\Controller\Console;

use Application\Controller\Plugin\Pic;
use Application\DuplicateFinder;
use Application\HostManager;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Service\SpecificationsService;
use Application\Service\TelegramService;
use Autowp\Message\MessageService;
use Autowp\TextStorage;
use Autowp\User\Auth\Adapter\Id as IdAuthAdapter;
use Autowp\User\Model\User;
use Laminas\Authentication\AuthenticationService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Uri\UriFactory;

use function htmlspecialchars;
use function sleep;
use function sprintf;
use function urlencode;
use function var_dump;

use const PHP_EOL;

/**
 * @method Pic pic()
 * @method void log(string $message, array $objects)
 * @method string language()
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class CatalogueController extends AbstractActionController
{
    private ItemParent $itemParent;

    private PictureItem $pictureItem;

    private TextStorage\Service $textStorage;

    private MessageService $message;

    private Item $itemModel;

    private Picture $picture;

    private User $userModel;

    private SpecificationsService $specService;

    private HostManager $hostManager;

    private TelegramService $telegram;

    private DuplicateFinder $duplicateFinder;

    public function __construct(
        ItemParent $itemParent,
        PictureItem $pictureItem,
        SpecificationsService $specService,
        HostManager $hostManager,
        TelegramService $telegram,
        MessageService $message,
        TextStorage\Service $textStorage,
        DuplicateFinder $duplicateFinder,
        Item $itemModel,
        Picture $picture,
        User $userModel
    ) {
        $this->itemParent      = $itemParent;
        $this->pictureItem     = $pictureItem;
        $this->specService     = $specService;
        $this->hostManager     = $hostManager;
        $this->telegram        = $telegram;
        $this->message         = $message;
        $this->textStorage     = $textStorage;
        $this->duplicateFinder = $duplicateFinder;
        $this->itemModel       = $itemModel;
        $this->picture         = $picture;
        $this->userModel       = $userModel;
    }

    public function refreshBrandVehicleAction(): string
    {
        $this->itemParent->refreshAllAuto();

        return "done\n";
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     */
    public function acceptOldUnsortedAction(): string
    {
        $select = $this->picture->getTable()->getSql()->select();
        $select
            ->where([
                'type = ?'   => Picture::UNSORTED_TYPE_ID,
                'status = ?' => Picture::STATUS_INBOX,
                'add_date < DATE_SUB(NOW(), INTERVAL 2 YEAR)',
            ])
            ->order('id');

        $rows = $this->picture->getTable()->selectWith($select);

        $userId = 9;

        $adapter = new IdAuthAdapter($this->userModel);
        $adapter->setIdentity($userId);

        $auth   = new AuthenticationService();
        $result = $auth->authenticate($adapter);

        if (! $result->isValid()) {
            var_dump('fail 1');
            return '';
        }

        if (! $auth->hasIdentity()) {
            var_dump('fail 2');
            return '';
        }

        foreach ($rows as $picture) {
            print $picture['id'] . PHP_EOL;

            $previousStatusUserId = $picture['change_status_user_id'];

            $isFirstTimeAccepted = false;
            $success             = $this->picture->accept($picture['id'], $userId, $isFirstTimeAccepted);
            if ($success && $isFirstTimeAccepted) {
                $owner = $this->userModel->getRow((int) $picture['owner_id']);
                if ($owner && ($owner['id'] !== $userId)) {
                    $uri = $this->hostManager->getUriByLanguage($owner['language']);

                    if (! $uri) {
                        $uri = UriFactory::factory('https://www.autowp.ru');
                    }

                    $uri->setPath('/picture/' . urlencode($picture['identity']))->toString();

                    $message = sprintf(
                        $this->translate('pm/your-picture-accepted-%s', 'default', $owner['language']),
                        $uri->toString()
                    );

                    $this->message->send(null, $owner['id'], $message);
                }

                $this->telegram->notifyPicture($picture['id']);
            }

            if ($previousStatusUserId !== $userId) {
                $prevUser = $this->userModel->getRow((int) $previousStatusUserId);
                if ($prevUser) {
                    $uri = $this->hostManager->getUriByLanguage($prevUser['language']);

                    $uri->setPath('/picture/' . urlencode($picture['identity']))->toString();

                    $message = sprintf(
                        'Принята картинка %s',
                        $uri->toString()
                    );
                    $this->message->send(null, $prevUser['id'], $message);
                }
            }

            $this->log(sprintf(
                'Картинка %s принята',
                htmlspecialchars($this->pic()->name($picture, $this->language()))
            ), [
                'pictures' => $picture['id'],
            ]);

            sleep(20);
        }

        return "done\n";
    }

    public function rebuildCarOrderCacheAction(): string
    {
        $paginator = $this->itemModel->getPaginator([
            'columns' => ['id'],
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
