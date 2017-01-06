<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\HostManager;
use Application\Model\BrandVehicle;
use Application\Model\DbTable;
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

    private $textStorage;

    public function __construct(
        BrandVehicle $brandVehicle,
        PictureItem $pictureItem,
        SpecificationsService $specService,
        HostManager $hostManager,
        TelegramService $telegram,
        Message $message,
        $textStorage
    ) {
        $this->brandVehicle = $brandVehicle;
        $this->pictureItem = $pictureItem;
        $this->specService = $specService;
        $this->hostManager = $hostManager;
        $this->telegram = $telegram;
        $this->message = $message;
        $this->textStorage = $textStorage;
    }

    public function refreshBrandVehicleAction()
    {
        $this->brandVehicle->refreshAllAuto();

        Console::getInstance()->writeLine("done");
    }

    public function migrateEnginesAction()
    {
        $groupTable = new DbTable\Twins\Group();
        $groupVehicleTable = new DbTable\Twins\GroupVehicle();

        $itemTable = new DbTable\Vehicle();
        $itemLangTable = new DbTable\Vehicle\Language();

        $itemParentTable = new DbTable\Vehicle\ParentTable();

        $db = $itemParentTable->getAdapter();

        $groupRows = $groupTable->fetchAll();

        foreach ($groupRows as $groupRow) {
            print $groupRow->id . PHP_EOL;

            $itemRow = $itemTable->fetchRow([
                'migration_group_id = ?' => $groupRow->id
            ]);
            if (!$itemRow) {
                $itemRow = $itemTable->createRow([
                    'migration_group_id' => $groupRow->id,
                    'name'               => $groupRow->name,
                    'item_type_id'       => DbTable\Item\Type::TWINS,
                    'catname'            => null,
                    'body'               => '',
                    'produced_exactly'   => 0,
                    'add_datetime'       => $groupRow->add_datetime,
                    'is_group'           => 1
                ]);
                $itemRow->save();
            }

            $groupVehicleRows = $groupVehicleTable->fetchAll([
                'twins_group_id = ?' => $groupRow->id,
            ]);

            foreach ($groupVehicleRows as $groupVehicleRow) {
                $vehicleRow = $itemTable->fetchRow([
                    'id = ?' => $groupVehicleRow->item_id
                ]);

                if ($vehicleRow) {
                    $itemParentTable->addParent($vehicleRow, $itemRow);

                    $itemTable->updateInteritance($vehicleRow);
                }
            }

            if ($groupRow->text_id) {
                $text = $this->textStorage->getText($groupRow->text_id);
                $language = null;

                if (!$text) {
                    $language = 'en';
                }

                if (preg_match('|^[[:space:] a-zA-ZІ½²³°®™‐€ÚÖćčśãéëóüòäáéâàèíßôĕłа́øęąñïêŠŻŽÝ~0-9±Ⅲº<>∙­·:;.,!?…`£#​​*«»×&=’()%"“”$–—+\\\\\'/\[\]_№-]+$|isu', $text)) {
                    $language = 'en';
                }

                if (preg_match('|^[[:space:] а-яА-Яa-zёЁA-ZІ½²³°®™‐€ÚÖćčśãéëóüòäáéâàèíßôĕłа́øęąñïêŠŻŽÝ~0-9±Ⅲº<>∙­·:;.,!?…`£#​​*«»×&=’()%"“”$–—+\\\\\'/\[\]_№-]+$|isu', $text)) {
                    $language = 'ru';
                }

                print $groupRow->text_id . '#' . $language . PHP_EOL;

                if (!$language) {
                    print $text . PHP_EOL;
                    exit;
                }

                $langRow = $itemLangTable->fetchRow([
                    'item_id = ?'  => $itemRow->id,
                    'language = ?' => $language
                ]);
                if (!$langRow) {
                    $langRow = $itemLangTable->createRow([
                        'item_id'  => $itemRow->id,
                        'language' => $language,
                    ]);
                }

                $langRow->text_id = $groupRow->text_id;
                $langRow->save();
            }

            $db->query('
                insert into log_events_item (log_event_id, item_id)
                select log_event_id, ?
                from log_events_twins_groups
                where twins_group_id = ?
            ', [$itemRow->id, $groupRow->id]);
        }
    }

    public function acceptOldUnsortedAction()
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

            $success = $pictureTable->accept($this->pictureItem, $picture->id, $userId, $isFirstTimeAccepted);
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

        Console::getInstance()->writeLine("done");
    }
}
