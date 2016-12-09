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
            $this->specService->updateActualValues(1, $itemRow->id);
        }
        
        Console::getInstance()->writeLine("done");
        return;
        
        /*$itemTable = new \Application\Model\DbTable\Vehicle();
        $itemParentCache = new \Application\Model\DbTable\Vehicle\ParentCache();
        
        foreach ($itemTable->fetchAll(['item_type_id = 2']) as $itemRow) {
            print $itemRow->id . "\n";
            $itemParentCache->rebuildCache($itemRow);
        }*/
        
        // add columns to cars: migration_engine_id, migration_parent_engine_id
        // add column type_id
        
        /*$engineTable = new \Application\Model\DbTable\Engine();
        $itemTable = new \Application\Model\DbTable\Vehicle();
        $carParentTable = new \Application\Model\DbTable\Vehicle\ParentTable();
        $brandEngineTable  = new \Application\Model\DbTable\BrandEngine();
        $pictureTable = new \Application\Model\DbTable\Picture;
        
        $db = $itemTable->getAdapter();
        
        $rows = $engineTable->fetchAll([
            //'id in (1454, 1450, 1448, 1449, 1451)',
            //'id not in (select parent_id from engines where parent_id)',
            //'id not in (select migration_engine_id from cars where migration_engine_id)'
        ]);
        
        foreach ($rows as $engineRow) {
            
            Console::getInstance()->writeLine($engineRow->id);
                       
            // create car 
            $item = $itemTable->fetchRow([
                'migration_engine_id = ?' => $engineRow->id,
            ]);
            if (!$item) {
                $item = $itemTable->createRow([
                    'name'                       => $engineRow->name,
                    'body'                       => '',
                    'item_type_id'               => 2,
                    'produced_exactly'           => 0
                ]);
            }
            $item->setFromArray([
                'migration_engine_id'        => $engineRow->id,
                'migration_parent_engine_id' => $engineRow->parent_id,
            ]);
            $item->save();
            
            // link moved child engines
            $childItems = $itemTable->fetchAll([
                'migration_parent_engine_id = ?' => $engineRow->id
            ]);
            foreach ($childItems as $childItem) {
                if (!$item->is_group) {
                    $item->is_group = 1;
                    $item->save();
                }
                $carParentTable->addParent($childItem, $item);
            }
            
            // link moved parent engines
            if ($engineRow->parent_id) {
                $parentItems = $itemTable->fetchAll([
                    'migration_engine_id = ?' => $engineRow->parent_id
                ]);
                foreach ($parentItems as $parentItem) {
                    if (!$parentItem->is_group) {
                        $parentItem->is_group = 1;
                        $parentItem->save();
                    }
                    $carParentTable->addParent($item, $parentItem);
                }
            }
            
            // move brand-links
            $brandEngineRows = $brandEngineTable->fetchAll([
                'engine_id = ?' => $engineRow->id
            ]);
            foreach ($brandEngineRows as $brandEngineRow) {
                $this->brandVehicle->create($brandEngineRow->brand_id, $item->id);
            }
            
            // move pictures
            $pictureRows = $pictureTable->fetchAll([
                'engine_id = ?' => $engineRow->id
            ]);
            foreach ($pictureRows as $pictureRow) {
                $this->pictureItem->setPictureItems($pictureRow->id, [$item->id]);
            }
            
            // move log
            $db->query('
                INSERT IGNORE INTO log_events_cars (log_event_id, car_id)
                SELECT log_event_id, ? FROM log_events_engines
                WHERE engine_id = ?
            ', [$item->id, $engineRow->id]);
            
            // move specs
            $tables = [
                'attrs_user_values', 
                'attrs_user_values_float',
                'attrs_user_values_int',
                'attrs_user_values_list',
                'attrs_user_values_string',
                'attrs_values',
                'attrs_values_float',
                'attrs_values_int',
                'attrs_values_list',
                'attrs_values_string',
            ];
            foreach ($tables as $table) {
                $db->update($table, [
                    'item_id'      => $item->id,
                    'item_type_id' => 1
                ], [
                    'item_id = ?' => $engineRow->id,
                    'item_type_id = 3'
                ]);
            }

            // move engine cars
            $carsOnEngine = $itemTable->fetchAll([
                'engine_id = ?' => $engineRow->id
            ]);
            
            foreach ($carsOnEngine as $carOnEngine) {
                $carOnEngine->engine_item_id = $item->id;
                $carOnEngine->save();
            }
        }*/
        
        //Console::getInstance()->writeLine("done");
    }

    /*public function migratePictureItemAction()
    {
        $pictureTable = new \Application\Model\DbTable\Picture();


        $offset = 0;
        do {
            $rows = $pictureTable->fetchAll([
                'car_id is not null',
                'id > 800000'
            ], 'id', 500, $offset);

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

            $offset += 500;
            sleep(1);
        } while (true);
    }*/

    /*public function migrateVehicleTypeAction()
    {
        $vehicleTable = new \Application\Model\DbTable\Vehicle();
        $vehicleType = new \Application\Model\VehicleType();

        $rows = $vehicleTable->fetchAll([], 'id desc', 300);
        foreach ($rows as $vehicle) {
            $vehicleType->refreshInheritanceFromParents($vehicle->id);
        }*/

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

        /*Console::getInstance()->writeLine("done");
    }*/
}
