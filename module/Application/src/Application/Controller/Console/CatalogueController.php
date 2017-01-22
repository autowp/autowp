<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\DuplicateFinder;
use Application\HostManager;
use Application\Model\BrandVehicle;
use Application\Model\DbTable;
use Application\Model\DbTable\Picture;
use Autowp\Message\MessageService;
use Application\Model\PictureItem;
use Application\Service\SpecificationsService;
use Application\Service\TelegramService;

use Autowp\User\Auth\Adapter\Id as IdAuthAdapter;
use Autowp\User\Model\DbTable\User;

use Zend\Authentication\AuthenticationService;

use Zend_Db_Expr;

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

        Console::getInstance()->writeLine("done");
    }

    public function migrateEnginesAction()
    {
        $pictureTable = new DbTable\Picture();
        $imageStorage = $this->imageStorage();

        foreach ($pictureTable->fetchAll('id >= 332734', 'id') as $pictureRow) {
            print $pictureRow->id . PHP_EOL;
            $resolution = $imageStorage->getImageResolution($pictureRow->image_id);
            print_r($resolution);
            if ($resolution) {
                $pictureRow->dpi_x = $resolution['x'];
                $pictureRow->dpi_y = $resolution['y'];
                $pictureRow->save();
            }
        }

        /*$itemTable = new DbTable\Item();
        $itemLangTable = new DbTable\Item\Language();
        $itemPointTable = new DbTable\Item\Point();

        $linkTable = new DbTable\Item\Link();

        $itemParentCacheTable = new DbTable\Item\ParentCache();

        $museumTable = new DbTable\Museum();

        foreach ($museumTable->fetchAll(null, 'id') as $museumRow) {
            print $museumRow->id . PHP_EOL;

            $itemRow = $itemTable->fetchRow([
                'migration_museum_id = ?' => $museumRow->id
            ]);
            if (!$itemRow) {
                $itemRow = $itemTable->createRow([
                    'migration_museum_id' => $museumRow->id,
                    'name'               => $museumRow->name,
                    'item_type_id'       => DbTable\Item\Type::MUSEUM,
                    'body'               => '',
                    'produced_exactly'   => 0,
                    'is_group'           => 1,
                ]);
                $itemRow->save();
            }

            if ($museumRow->point) {
                $itemPointRow = $itemPointTable->fetchRow([
                    'item_id = ?' => $itemRow->id
                ]);
                if (!$itemPointRow) {
                    $itemPointRow = $itemPointTable->createRow([
                        'item_id' => $itemRow->id
                    ]);
                }

                $itemPointRow->point = $museumRow->point;
                $itemPointRow->save();
            }

            if ($museumRow->description) {
                $text = $museumRow->description;
                $language = null;

                if (!$text) {
                    $language = 'en';
                }

                if (preg_match('|^[[:space:] a-zA-ZІ½²³°®™„”‐€ŤÚőçÚÖćčśșãéëóüòäáéâàèíßôĕłа́øęąñïêŠŻŽÝ~0-9±Ⅲº<>∙­·:;.,!?…`£#​​*«»×&=’()%"“”$–—+\\\\\'/\[\]_№-]+$|isu', $text)) {
                    $language = 'en';
                }

                if (preg_match('|^[[:space:] а-яА-Яa-zёЁA-ZІ½²³°®™„”‐€ŤÚőçÚÖćčśșãéëóüòäáéâàèíßôĕłа́øęąñïêŠŻŽÝ~0-9±Ⅲº<>∙­·:;.,!?…`£#​​*«»×&=’()%"“”$–—+\\\\\'/\[\]_№-]+$|isu', $text)) {
                    $language = 'ru';
                }

                print $museumRow->id . '#' . $language . PHP_EOL;

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

                if (!$langRow->text_id) {
                    $textId = $this->textStorage->createText($text, 9);
                    $langRow->text_id = $textId;
                }

                $langRow->save();
            }

            if ($museumRow->url) {
                $linkRow = $linkTable->fetchRow([
                    'item_id = ?' => $itemRow->id
                ]);

                if (! $linkRow) {
                    $linkRow = $linkTable->createRow([
                        'item_id' => $itemRow->id,
                        'type'    => 'default',
                        'url'     => $museumRow->url,
                        'name'    => ''
                    ]);
                    $linkRow->save();
                }
            }

            if ($museumRow->img) {

                $alreadyDone = (bool)$pictureTable->fetchRow([
                    'image_id = ?' => $museumRow->img
                ]);

                if (!$alreadyDone) {
                    $image = $imageStorage->getImage($museumRow->img);

                    // add record to db
                    $picture = $pictureTable->createRow([
                        'image_id'      => $museumRow->img,
                        'width'         => $image->getWidth(),
                        'height'        => $image->getHeight(),
                        'owner_id'      => 9,
                        'add_date'      => '2014-01-01 00:00:00',
                        'filesize'      => $image->getFileSize(),
                        'status'        => DbTable\Picture::STATUS_ACCEPTED,
                        'removing_date' => null,
                        'ip'            => new Zend_Db_Expr('inet6_aton("127.0.0.1")'),
                        'identity'      => $pictureTable->generateIdentity(),
                        'replace_picture_id' => null,
                    ]);
                    $picture->save();

                    $this->pictureItem->setPictureItems($picture->id, [$itemRow->id]);
                }
            }

            $itemParentCacheTable->rebuildCache($itemRow);
        }*/

        /*



        $rows = $pictureTable->fetchAll('id >= 238504', 'id');

        foreach ($rows as $row) {
            print $row->id . PHP_EOL;

            $this->duplicateFinder->updateDistance($row->id);
        }*/


        /*

        $itemParentTable = new DbTable\Item\ParentTable();
        $itemParentLanguageTable = new DbTable\Item\ParentLanguage();




        $factoryTable = new DbTable\Factory();
        $factoryCarTable = new DbTable\FactoryCar();

        $db = $factoryTable->getAdapter();

        foreach ($factoryTable->fetchAll(null, null) as $factoryRow) {






            $db->query('
                insert ignore into log_events_item (log_event_id, item_id)
                select log_event_id, ?
                from log_events_factory
                where factory_id = ?
            ', [$itemRow->id, $factoryRow->id]);

            $factoryCarRows = $factoryCarTable->fetchAll([
                'factory_id = ?' => $factoryRow->id
            ]);
            foreach ($factoryCarRows as $factoryCarRow) {

                print 'car=' . $factoryCarRow->item_id . PHP_EOL;

                $vehicleRow = $itemTable->fetchRow([
                    'id = ?' => $factoryCarRow->item_id
                ]);

                $this->brandVehicle->create($itemRow->id, $vehicleRow->id);

                $itemTable->updateInteritance($vehicleRow);
            }

            $itemParentCacheTable->rebuildCache($itemRow);


            $pictureRows = $pictureTable->fetchAll([
                'type = ?'       => DbTable\Picture::FACTORY_TYPE_ID,
                'factory_id = ?' => $factoryRow->id
            ], 'id');

            foreach ($pictureRows as $picture) {

                print 'picture=' . $picture->id . PHP_EOL;

                $this->pictureItem->setPictureItems($picture->id, [$itemRow->id]);
                $this->pictureItem->setProperties($picture->id, $itemRow->id, [
                    'perspective' => 16
                ]);

                $picture->type = DbTable\Picture::VEHICLE_TYPE_ID;
                $picture->save();
            }
        }*/

        /*$db = $itemParentTable->getAdapter();

        $itemParentRows = $itemParentTable->fetchAll([
            'length(name) > 0'
        ], 'item_id');

        foreach ($itemParentRows as $itemParentRow) {
            print_r($itemParentRow->toArray());

            $text = $itemParentRow->name;

            $language = null;

            if (preg_match('|^[[:space:] a-zA-ZІ½ª²³°®™„”‐€ŤÚőçÚÖńćčśșãéëóüòäáéâàèíßôĕłа́øęąñïêŠŻŽÝ~0-9±Ⅲº<>∙­·:;.,!?…`£#​​*«»×&=’()%"“”$–—+\\\\\'/\[\]_№-]+$|isu', $text)) {
                $language = 'en';
            }

            if (preg_match('|^[[:space:] а-яА-Яa-zёЁA-ZІ½ª²³°®™„”‐€ŤÚőçÚÖńćčśșãéëóüòäáéâàèíßôĕłа́øęąñïêŠŻŽÝ~0-9±Ⅲº<>∙­·:;.,!?…`£#​​*«»×&=’()%"“”$–—+\\\\\'/\[\]_№-]+$|isu', $text)) {
                $language = 'ru';
            }

            if (! $language) {
                print $text . PHP_EOL;
                exit;
            }

            $itemParentLanguageRow = $itemParentLanguageTable->fetchRow([
                'item_id = ?' => $itemParentRow->item_id,
                'parent_id = ?' => $itemParentRow->parent_id,
                'language = ?' => $language
            ]);

            if (! $itemParentLanguageRow) {
                $itemParentLanguageRow = $itemParentLanguageTable->createRow([
                    'item_id' => $itemParentRow->item_id,
                    'parent_id' => $itemParentRow->parent_id,
                    'language' => $language
                ]);
            }

            if (! $itemParentLanguageRow->name) {
                $itemParentLanguageRow->name = $text;
                $itemParentLanguageRow->save();
            }

            print_r($itemParentLanguageRow->toArray());
        }*/

        /*$rows = $itemTable->fetchAll(['item_type_id = ?' => DbTable\Item\Type::BRAND]);
        foreach ($rows as $row) {
            print $row->id . PHP_EOL;
            $itemParentCacheTable->rebuildCache($row);
        }*/

        /*$brandRows = $brandTable->fetchAll(null, 'id');

        foreach ($brandRows as $brandRow) {





        }*/

        // parent_brand_id
        /**/

        // brand_language
        /*$brandLanguageTable = new DbTable\BrandLanguage();
        foreach ($brandLanguageTable->fetchAll(null, 'brand_id') as $brandLanguageRow) {

            print $brandLanguageRow->brand_id . ' ' . $brandLanguageRow->language . PHP_EOL;

            $brandItemRow = $itemTable->fetchRow([
                'migration_brand_id = ?' => $brandLanguageRow->brand_id
            ]);

            $langRow = $itemLangTable->fetchRow([
                'item_id = ?'  => $brandItemRow->id,
                'language = ?' => $brandLanguageRow->language
            ]);
            if (!$langRow) {
                $langRow = $itemLangTable->createRow([
                    'item_id'  => $brandItemRow->id,
                    'language' => $brandLanguageRow->language,
                ]);
            }
            $langRow->name = $brandLanguageRow->name;
            $langRow->save();

            print $langRow->item_id . ' ' . $langRow->language . PHP_EOL;
        }*/

        // brand_item
        /*$brandItemTable = new DbTable\BrandItem();
        foreach ($brandItemTable->fetchAll(['brand_id >= 58'], 'brand_id') as $brandItemRow) {

            print $brandItemRow->brand_id . ' ' . $brandItemRow->item_id . PHP_EOL;

            $brandRow = $itemTable->fetchRow([
                'migration_brand_id = ?' => $brandItemRow->brand_id
            ]);

            $vehicleRow = $itemTable->fetchRow([
                'id = ?' => $brandItemRow->item_id
            ]);

            $itemParentTable->addParent($vehicleRow, $brandRow, [
                'type'           => $brandItemRow->type,
                'catname'        => $brandItemRow->catname,
                'manual_catname' => $brandItemRow->is_auto,
                'type'           => $brandItemRow->type
            ]);

            //print $brandRow->id . ' ' . $vehicleRow->id . PHP_EOL;

            //$itemTable->updateInteritance($vehicleRow);
        }*/

        /**/

        // brand_alias

        // brand_vehicle_language
        /*$brandVehicleLanguageTable = new DbTable\Brand\VehicleLanguage();
        $itemParentLanguageTable = new \Zend_Db_Table([
            'name' => 'item_parent_language',
            'primary' => [
                'item_id', 'parent_id', 'language'
            ]
        ]);

        $rows = $brandVehicleLanguageTable->fetchAll(null, 'brand_id');
        foreach ($rows as $brandVehicleLanguageRow) {
            print_r($brandVehicleLanguageRow->toArray());

            $brandRow = $itemTable->fetchRow([
                'migration_brand_id = ?' => $brandVehicleLanguageRow->brand_id
            ]);

            if (!$brandRow) {
                throw new Exception("Brand not found");
            }

            $itemParentLanguageRow = $itemParentLanguageTable->fetchRow([
                'item_id = ?'   => $brandVehicleLanguageRow->vehicle_id,
                'parent_id = ?' => $brandRow->id,
                'language = ?'  => $brandVehicleLanguageRow->language
            ]);
            if (!$itemParentLanguageRow) {
                $itemParentLanguageRow = $itemParentLanguageTable->createRow([
                    'item_id'   => $brandVehicleLanguageRow->vehicle_id,
                    'parent_id' => $brandRow->id,
                    'language'  => $brandVehicleLanguageRow->language
                ]);
            }

            $itemParentLanguageRow->setFromArray([
                'name'    => $brandVehicleLanguageRow->name,
                'is_auto' => $brandVehicleLanguageRow->is_auto,
            ]);
            $itemParentLanguageRow->save();

            print_r($itemParentLanguageRow->toArray());
        }*/
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

        Console::getInstance()->writeLine("done");
    }
}
