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
        
        $itemRows = $itemTable->fetchAll([
            'item_type_id = ?' => DbTable\Item\Type::CATEGORY
        ]);
        
        foreach ($itemRows as $itemRow) {
            print $itemRow->id . PHP_EOL;
            $itemRow->updateOrderCache();
        }
        
        /*$categoryTable = new DbTable\Category();
        $categoryLangTable = new DbTable\Category\Language();
        $categoryItemTable = new DbTable\Category\Vehicle();
        
        $itemTable = new DbTable\Vehicle();
        $itemLangTable = new DbTable\Vehicle\Language();
        
        $itemParentTable = new DbTable\Vehicle\ParentTable();
        
        $categoryRows = $categoryTable->fetchAll();
        
        foreach ($categoryRows as $categoryRow) {
            print $categoryRow->id . PHP_EOL;
            
            $itemRow = $itemTable->fetchRow([
                'migration_category_id = ?' => $categoryRow->id
            ]);
            if (!$itemRow) {
                $itemRow = $itemTable->createRow([
                    'migration_category_id' => $categoryRow->id,
                    'name'                  => $categoryRow->name,
                    'item_type_id'          => DbTable\Item\Type::CATEGORY,
                    'catname'               => $categoryRow->catname,
                    'body'                  => '',
                    'produced_exactly'      => 0
                ]);
                $itemRow->save();
            }*/
            
            /*$categoryLangRows = $categoryLangTable->fetchAll([
                'category_id = ?' => $categoryRow->id
            ]);
            
            foreach ($categoryLangRows as $categoryLangRow) {
                $itemLangRow = $itemLangTable->fetchRow([
                    'car_id = ?'   => $itemRow->id,
                    'language = ?' => $categoryLangRow->language
                ]);
                if (!$itemLangRow) {
                    $itemLangRow = $itemLangTable->createRow([
                        'car_id'   => $itemRow->id,
                        'language' => $categoryLangRow->language,
                        'name'     => $categoryLangRow->name,
                        'text_id'  => $categoryLangRow->text_id
                    ]);
                    $itemLangRow->save();
                }
            }*/
            
            /*if ($categoryRow->parent_id) {
                
                $parentItemRow = $itemTable->fetchRow([
                    'migration_category_id = ?' => $categoryRow->parent_id
                ]);
                
                if ($parentItemRow) {
                    $parentItemRow->is_group = 1;
                    $parentItemRow->save();
                    
                    //$itemParentTable->addParent($itemRow, $parentItemRow);
                    
                    $langData = [];
                    foreach ($categoryLangRows as $categoryLangRow) {
                        $langData[$categoryLangRow->language] = [
                            'name' => $categoryLangRow->short_name
                        ];
                    }
                    
                    $itemParentTable->setParentOptions($itemRow, $parentItemRow, [
                        'name'      => $categoryRow->short_name,
                        'languages' => $langData
                    ]);
                    
                    //$itemTable->updateInteritance($itemRow);
                }
            }*/
            
            /*$categoryItemRows = $categoryItemTable->fetchAll([
                'category_id = ?' => $categoryRow->id,
            ]);
            
            foreach ($categoryItemRows as $categoryItemRow) {
                if (!$itemRow->is_group) {
                    $itemRow->is_group = 1;
                    $itemRow->save();
                }
                
                $vehicleRow = $itemTable->fetchRow([
                    'id = ?' => $categoryItemRow->item_id
                ]);
                
                if ($vehicleRow) {
                    $itemParentTable->addParent($vehicleRow, $itemRow);
                    
                    //$itemTable->updateInteritance($vehicleRow);
                }
            }
        }*/
        
        /*$itemTable = new DbTable\Vehicle();
        $itemLangTable = new DbTable\Vehicle\Language();
        
        $itemRows = $itemTable->fetchAll([
            'full_text_id IS NOT NULL'
        ]);
        
        foreach ($itemRows as $itemRow) {
            $text = $this->textStorage->getText($itemRow->full_text_id);
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
            
            print $itemRow->full_text_id . '#' . $language . PHP_EOL;
            
            if (!$language) {
                print $text . PHP_EOL;
                exit;
            }
            
            $langRow = $itemLangTable->fetchRow([
                'car_id = ?'   => $itemRow->id,
                'language = ?' => $language
            ]);
            if (!$langRow) {
                $langRow = $itemLangTable->createRow([
                    'car_id'   => $itemRow->id,
                    'language' => $language,
                ]);
            }
            
            $langRow->full_text_id = $itemRow->full_text_id;
            $langRow->save();
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
