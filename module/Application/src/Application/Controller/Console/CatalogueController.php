<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\DuplicateFinder;
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
        
        $rows = $pictureTable->fetchAll('id >= 210834', 'id');
        
        $this->duplicateFinder->updateDistance(359979);
        $this->duplicateFinder->updateDistance(1045801);
        
        foreach ($rows as $row) {
            print $row->id . PHP_EOL;
            
            $this->duplicateFinder->updateDistance($row->id);
        }
        
        
        /*$itemTable = new DbTable\Item();
        $itemLangTable = new DbTable\Item\Language();

        $itemParentTable = new DbTable\Item\ParentTable();
        $itemParentLanguageTable = new DbTable\Item\ParentLanguage();
        $itemParentCacheTable = new DbTable\Item\ParentCache();
        $itemPointTable = new DbTable\Item\Point();
        

        $factoryTable = new DbTable\Factory();
        $factoryCarTable = new DbTable\FactoryCar();

        $db = $factoryTable->getAdapter();

        foreach ($factoryTable->fetchAll(null, null) as $factoryRow) {
            print $factoryRow->id . PHP_EOL;

            $itemRow = $itemTable->fetchRow([
                'migration_factory_id = ?' => $factoryRow->id
            ]);
            if (!$itemRow) {
                $itemRow = $itemTable->createRow([
                    'migration_factory_id' => $factoryRow->id,
                    'name'               => $factoryRow->name,
                    'item_type_id'       => DbTable\Item\Type::FACTORY,
                    'body'               => '',
                    'produced_exactly'   => 0,
                    'is_group'           => 1,
                    'begin_year'         => $factoryRow->year_from ? $factoryRow->year_from : null,
                    'end_year'           => $factoryRow->year_to ? $factoryRow->year_to : null
                ]);
                $itemRow->save();
            }

            if ($factoryRow->text_id) {
                $text = $this->textStorage->getText($factoryRow->text_id);
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

                print $factoryRow->text_id . '#' . $language . PHP_EOL;

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

                $langRow->text_id = $factoryRow->text_id;
                $langRow->save();
            }

            if ($factoryRow->point) {
                $itemPointRow = $itemPointTable->fetchRow([
                    'item_id = ?' => $itemRow->id
                ]);
                if (!$itemPointRow) {
                    $itemPointRow = $itemPointTable->createRow([
                        'item_id' => $itemRow->id
                    ]);
                }

                $itemPointRow->point = $factoryRow->point;
                $itemPointRow->save();
            }

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
