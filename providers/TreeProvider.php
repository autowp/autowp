<?php

require_once 'Zend/Tool/Project/Provider/Abstract.php';
require_once 'Zend/Tool/Project/Provider/Exception.php';

class TreeProvider extends Zend_Tool_Project_Provider_Abstract
{
    public function parents($carId = null)
    {
        $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION); //load .zfproject.xml

        $bootstrapResource = $this->_loadedProfile->search('BootstrapFile'); //get application bootstrap

        /* @var $zendApp Zend_Application */
        $zendApp = $bootstrapResource->getApplicationInstance(); //initialize application instance

        $zendApp
            ->bootstrap('phpEnvoriment')
            ->bootstrap('autoloader')
            ->bootstrap('db');

        $carTable = new Cars();
        $cpcTable = new Car_Parent_Cache();

        $adapter = $carTable->getAdapter();
        $select = $adapter->select()
            ->from($carTable->info('name'), 'id')
            ->order('id');

        if ($carId) {
            $select->where('id = ?', $carId);
        }

        $ids = $adapter->fetchCol($select);

        foreach ($ids as $id) {
            print "\r" . $id;
            $car = $carTable->find($id)->current();
            $updates = $cpcTable->rebuildCache($car);
        }

        print "ok\n";
        print $updates . " updates\n";
    }

    public function models($carId = null)
    {
        $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION); //load .zfproject.xml

        $bootstrapResource = $this->_loadedProfile->search('BootstrapFile'); //get application bootstrap

        /* @var $zendApp Zend_Application */
        $zendApp = $bootstrapResource->getApplicationInstance(); //initialize application instance

        $zendApp
            ->bootstrap('phpEnvoriment')
            ->bootstrap('autoloader')
            ->bootstrap('db');

        $carTable = new Cars();
        $modelCarTable = new Models_Cars();

        $adapter = $carTable->getAdapter();

        $select = $adapter->select()
            ->from($carTable->info('name'), 'id')
            ->order('id');

        if ($carId) {
            $select->where('cars.id = ?', $carId);
        }

        $ids = $adapter->fetchCol($select);

        $toCheckNextTime = array_flip($ids);

        while (count($toCheckNextTime) > 0) {

            $toCheck = $toCheckNextTime;
            $toCheckNextTime = array();

            foreach ($toCheck as $id => $null) {
                $carRow = $carTable->find($id)->current();

                if (!$carRow) {
                    throw new Exception("Car row not found `$id`");
                }

                unset($toCheckNextTime[$id]);

                $updates = $modelCarTable->updateInheritace($carRow);
                if ($updates) {
                    print "Queue to check childs of $id\n";

                    $adapter = $carTable->getAdapter();
                    $ids = $adapter->fetchCol(
                        $adapter->select()
                            ->from($carTable->info('name'), 'id')
                            ->join('car_parent', 'cars.id = car_parent.parent_id', null)
                            ->where('car_parent.parent_id = ?', $id)
                    );
                    foreach ($ids as $childId) {
                        $toCheckNextTime[$childId] = true;
                    }
                }
            }
        }

        print "ok\n";
    }

    public function modelTreeDuplicates($carId = null)
    {
        $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION); //load .zfproject.xml

        $bootstrapResource = $this->_loadedProfile->search('BootstrapFile'); //get application bootstrap

        /* @var $zendApp Zend_Application */
        $zendApp = $bootstrapResource->getApplicationInstance(); //initialize application instance

        $zendApp
            ->bootstrap('phpEnvoriment')
            ->bootstrap('autoloader')
            ->bootstrap('db');


        $modelCarTable = new Models_Cars();
        $carTable = new Cars();

        $adapter = $carTable->getAdapter();

        $select = $adapter->select()
            ->from(array('car1' => $carTable->info('name')), array('id1' => 'car1.id', 'id2' => 'car2.id', 'models_cars.model_id'))
            ->join('models_cars', 'car1.id = models_cars.car_id and not models_cars.inherited', null)
            ->join('car_parent', 'car1.id = car_parent.car_id', null)
            ->join(array('car2' => 'cars'), 'car_parent.parent_id = car2.id', null)
            ->join(array('models_parent' => 'models_cars'), 'car2.id = models_parent.car_id and not models_parent.inherited', null)
            ->where('car1.id <> car2.id')
            ->where('models_cars.model_id = models_parent.model_id');

        if ($carId) {
            $select->where('car1.id = ?', $carId);
        }

        foreach ($adapter->fetchAll($select) as $row) {
            $car1 = $carTable->find($row['id1'])->current();
            $car2 = $carTable->find($row['id2'])->current();

            print $car2->id . ' ' . $car2->getFullName() . PHP_EOL;
            print "\t" . $car1->id . ' ' . $car1->getFullName() . PHP_EOL;

            $modelCar1 = $modelCarTable->fetchRow(array(
                'model_id = ?' => $row['model_id'],
                'car_id = ?'   => $car1->id
            ));

            $modelCar2 = $modelCarTable->fetchRow(array(
                'model_id = ?' => $row['model_id'],
                'car_id = ?'   => $car2->id
            ));

            if (!$modelCar1 || !$modelCar2) {
                throw new Exception("Moel car not found");
            }

            $compatible = $modelCar1->type === $modelCar2->type
                       && $modelCar1->generation_id === $modelCar2->generation_id;

            if ($compatible) {
                $modelCarRow = $modelCarTable->fetchRow(array(
                    'model_id = ?' => $row['model_id'],
                    'car_id = ?'   => $car1->id
                ));
                $modelCarRow->inherited = true;
                $modelCarRow->save();
            } else {
                print 'Not compatible' . PHP_EOL;
            }
        }
    }

    public function catname()
    {
        $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION); //load .zfproject.xml

        $bootstrapResource = $this->_loadedProfile->search('BootstrapFile'); //get application bootstrap

        /* @var $zendApp Zend_Application */
        $zendApp = $bootstrapResource->getApplicationInstance(); //initialize application instance

        $zendApp
            ->bootstrap('phpEnvoriment')
            ->bootstrap('autoloader')
            ->bootstrap('db');

        $carParentTable = new Car_Parent();

        foreach ($carParentTable->fetchAll() as $row) {
            if (strlen($row->catname) <= 0) {
                print $row->car_id . "\r";
                $row->catname = $row->car_id;
                $row->save();
            }
        }

        print "ok\n";
    }
}
