<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\BrandVehicle;
use Application\Model\PictureItem;

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
        PictureItem $pictureItem
    ) {
        $this->brandVehicle = $brandVehicle;
        $this->pictureItem = $pictureItem;
    }

    public function refreshBrandVehicleAction()
    {
        $this->brandVehicle->refreshAllAuto();

        Console::getInstance()->writeLine("done");
    }
    
    public function migratePictureItemAction()
    {
        $pictureTable = new \Application\Model\DbTable\Picture();
        
        
        $offset = 0;
        do { 
            $rows = $pictureTable->fetchAll([
                'car_id is not null'
            ], 'id', 300, $offset);
            
            if (count($rows) <= 0) {
                break;
            }
            
            foreach ($rows as $row) {
                Console::getInstance()->writeLine($row->id);
                $this->pictureItem->setPictureItems($row->id, $row->car_id ? [$row->car_id] : null);
                
                $crop = null;
                if ($row->crop_left || $row->crop_top || $row->crop_width || $row->crop_height) {
                    $crop = [
                        'left'   => $row->crop_left,
                        'top'    => $row->crop_top,
                        'width'  => $row->crop_width,
                        'height' => $row->crop_height
                    ];
                }
                
                $this->pictureItem->setProperties($row->id, $row->car_id, [
                    'perspective' => $row->perspective_id,
                    'crop'        => $crop
                ]);
            }
            
            $offset += 300;
            
        } while(true);
    }

    public function migrateVehicleTypeAction()
    {
        $vehicleTable = new \Application\Model\DbTable\Vehicle();
        $vehicleType = new \Application\Model\VehicleType();

        $rows = $vehicleTable->fetchAll([], 'id desc', 300);
        foreach ($rows as $vehicle) {
            $vehicleType->refreshInheritanceFromParents($vehicle->id);
        }

        /*$rows = $vehicleTable->fetchAll([
            'car_type_id',
            'not car_type_inherit'
        ], 'id');
        foreach ($rows as $vehicle) {
            Console::getInstance()->writeLine($vehicle->id);
            $vehicleType->setVehicleTypes($vehicle->id, [$vehicle->car_type_id]);
        }

        Console::getInstance()->writeLine("done");*/

        // limousines to cars and limousines
        /*$rows = $vehicleTable->fetchAll([
            'car_type_id = ?' => 11,
            'not car_type_inherit'
        ], 'id');
        foreach ($rows as $vehicle) {
            Console::getInstance()->writeLine($vehicle->id);
            $vehicleType->setVehicleTypes($vehicle->id, [11, 29]);
        }*/

        // offroad-limousines to offroad and limousines
        /*$rows = $vehicleTable->fetchAll([
            'car_type_id = ?' => 30,
            'not car_type_inherit'
        ], 'id');
        foreach ($rows as $vehicle) {
            Console::getInstance()->writeLine($vehicle->id);
            $vehicleType->setVehicleTypes($vehicle->id, [14, 11]);
        }*/

        // pickup to cars and pickup
        /*$rows = $vehicleTable->fetchAll([
            'car_type_id = ?' => 12,
            'not car_type_inherit'
        ], 'id');
        foreach ($rows as $vehicle) {
            Console::getInstance()->writeLine($vehicle->id);
            $vehicleType->setVehicleTypes($vehicle->id, [12, 29]);
        }*/

        // offroad-pickup  to offroad and pickup
        /*$rows = $vehicleTable->fetchAll([
            'car_type_id = ?' => 24,
            'not car_type_inherit'
        ], 'id');
        foreach ($rows as $vehicle) {
            Console::getInstance()->writeLine($vehicle->id);
            $vehicleType->setVehicleTypes($vehicle->id, [14, 12]);
        }*/

        // cabrio to cars and cabrio
        /*$rows = $vehicleTable->fetchAll([
            'car_type_id = ?' => 3,
            'not car_type_inherit'
        ], 'id');
        foreach ($rows as $vehicle) {
            Console::getInstance()->writeLine($vehicle->id);
            $vehicleType->setVehicleTypes($vehicle->id, [3, 29]);
        }*/

        // offroad-cabrio to offroad and cabrio
        /*$rows = $vehicleTable->fetchAll([
            'car_type_id = ?' => 23,
            'not car_type_inherit'
        ], 'id');
        foreach ($rows as $vehicle) {
            Console::getInstance()->writeLine($vehicle->id);
            $vehicleType->setVehicleTypes($vehicle->id, [14, 3]);
        }*/

        // crossover-cabrio to crossover and cabrio
        /*$rows = $vehicleTable->fetchAll([
            'car_type_id = ?' => 42,
            'not car_type_inherit'
        ], 'id');
        foreach ($rows as $vehicle) {
            Console::getInstance()->writeLine($vehicle->id);
            $vehicleType->setVehicleTypes($vehicle->id, [9, 3]);
        }*/

        // universal to cars and universal
        /*$rows = $vehicleTable->fetchAll([
            'car_type_id = ?' => 10,
            'not car_type_inherit'
        ], 'id');
        foreach ($rows as $vehicle) {
            Console::getInstance()->writeLine($vehicle->id);
            $vehicleType->setVehicleTypes($vehicle->id, [10, 29]);
        }*/

        // offroad-universal to offroad and universal
        /*$rows = $vehicleTable->fetchAll([
            'car_type_id = ?' => 31,
            'not car_type_inherit'
        ], 'id');
        foreach ($rows as $vehicle) {
            Console::getInstance()->writeLine($vehicle->id);
            $vehicleType->setVehicleTypes($vehicle->id, [14, 10]);
        }*/

        Console::getInstance()->writeLine("done");
    }
}
