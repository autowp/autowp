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
        $itemTable = new DbTable\Vehicle();
        $itemLangTable = new DbTable\Vehicle\Language();

        $itemParentTable = new DbTable\Vehicle\ParentTable();
        $itemParentLanguageTable = new DbTable\Item\ParentLanguage();
        $itemParentCacheTable = new DbTable\Vehicle\ParentCache();
        

        $db = $itemParentTable->getAdapter();
        
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
            
            if (!$language) {
                print $text . PHP_EOL;
                exit;
            }
            
            $itemParentLanguageRow = $itemParentLanguageTable->fetchRow([
                'item_id = ?' => $itemParentRow->item_id,
                'parent_id = ?' => $itemParentRow->parent_id,
                'language = ?' => $language
            ]);
            
            if (!$itemParentLanguageRow) {
                $itemParentLanguageRow = $itemParentLanguageTable->createRow([
                    'item_id' => $itemParentRow->item_id,
                    'parent_id' => $itemParentRow->parent_id,
                    'language' => $language
                ]);
            }
            
            if (!$itemParentLanguageRow->name) {
                $itemParentLanguageRow->name = $text;
                $itemParentLanguageRow->save();
            }
            
            print_r($itemParentLanguageRow->toArray());
        }
        
        /*$rows = $itemTable->fetchAll(['item_type_id = ?' => DbTable\Item\Type::BRAND]);
        foreach ($rows as $row) {
            print $row->id . PHP_EOL;
            $itemParentCacheTable->rebuildCache($row);
        }*/

        /*$brandRows = $brandTable->fetchAll(null, 'id');

        foreach ($brandRows as $brandRow) {
            print $brandRow->id . PHP_EOL;

            $itemRow = $itemTable->fetchRow([
                'migration_brand_id = ?' => $brandRow->id
            ]);
            if (!$itemRow) {
                $itemRow = $itemTable->createRow([
                    'migration_brand_id' => $brandRow->id,
                    'name'               => $brandRow->name,
                    'full_name'          => $brandRow->full_name,
                    'item_type_id'       => DbTable\Item\Type::BRAND,
                    'catname'            => $brandRow->folder,
                    'position'           => $brandRow->position,
                    'body'               => '',
                    'produced_exactly'   => 0,
                    'is_group'           => 1,
                    'begin_year'         => $brandRow->from_year ? $brandRow->from_year : null,
                    'end_year'           => $brandRow->to_year ? $brandRow->to_year : null,
                    'logo_id'            => $brandRow->img ? $brandRow->img : null
                ]);
                $itemRow->save();
            }
            
            if ($brandRow->text_id) {
                $text = $this->textStorage->getText($brandRow->text_id);
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

                print $brandRow->text_id . '#' . $language . PHP_EOL;

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

                $langRow->text_id = $brandRow->text_id;
                $langRow->save();
            }

            $db->query('
                insert ignore into log_events_item (log_event_id, item_id)
                select log_event_id, ?
                from log_events_brands
                where brand_id = ?
            ', [$itemRow->id, $brandRow->id]);
        }*/
        
        // parent_brand_id
        /*$brandRows = $brandTable->fetchAll('parent_brand_id');
        foreach ($brandRows as $brandRow) {
            
            print $brandRow->id . PHP_EOL;
            
            $parentBrandItemRow = $itemTable->fetchRow([
                'migration_brand_id = ?' => $brandRow->parent_brand_id
            ]);
            
            $childBrandItemRow = $itemTable->fetchRow([
                'migration_brand_id = ?' => $brandRow->id
            ]);
            
            $itemParentTable->addParent($childBrandItemRow, $parentBrandItemRow);
            
            $itemTable->updateInteritance($childBrandItemRow);
        }*/
        
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
        
        /*$pictureTable = new DbTable\Picture();
        
        $rows = $pictureTable->fetchAll([
            'type = ?' => DbTable\Picture::UNSORTED_TYPE_ID
        ], 'id');
        
        foreach ($rows as $picture) {
            
            print $picture->id . PHP_EOL;
            
            $brandRow = $itemTable->fetchRow([
                'migration_brand_id = ?' => $picture->brand_id
            ]);
            
            if (!$brandRow) {
                throw new Exception("Brand not found");
            }
            
            $this->pictureItem->setPictureItems($picture->id, [$brandRow->id]);
            
            $picture->type = DbTable\Picture::VEHICLE_TYPE_ID;
            $picture->save();
        }*/
        
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
