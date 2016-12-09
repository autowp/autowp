<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\BrandVehicle;
use Application\Model\PictureItem;
use Application\Service\SpecificationsService;

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
        SpecificationsService $specService
    ) {
        $this->brandVehicle = $brandVehicle;
        $this->pictureItem = $pictureItem;
        $this->specService = $specService;
    }

    public function refreshBrandVehicleAction()
    {
        $this->brandVehicle->refreshAllAuto();

        Console::getInstance()->writeLine("done");
    }

    public function migrateEnginesAction()
    {
        $itemTable = new \Application\Model\DbTable\Vehicle();

        foreach ($itemTable->fetchAll(['item_type_id = 2']) as $itemRow) {
            print $itemRow->id . "\n";
            $this->specService->updateActualValues($itemRow->id);
        }

        Console::getInstance()->writeLine("done");
        return;

        //Console::getInstance()->writeLine("done");
    }
}
