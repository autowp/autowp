<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\BrandVehicle;

class CatalogueController extends AbstractActionController
{
    /**
     * @var BrandVehicle
     */
    private $brandVehicle;

    public function __construct(BrandVehicle $brandVehicle)
    {
        $this->brandVehicle = $brandVehicle;
    }

    public function refreshBrandVehicleAction()
    {
        $this->brandVehicle->refreshAllAuto();

        Console::getInstance()->writeLine("done");
    }
    
    public function migrateVehicleTypeAction()
    {
        $vehicleTable = new \Application\Model\DbTable\Vehicle();
        $vehicleType = new \Application\Model\VehicleType();
        
        /*$rows = $vehicleTable->fetchAll([], 'id desc', 200);
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
        
        Console::getInstance()->writeLine("done");
    }
}