<?php

class Moder_GraphController extends Zend_Controller_Action
{
    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder') ) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }

    public function indexAction()
    {

    }

    protected function _carNode($car)
    {
        $carParentTable = new Car_Parent();

        $adapter = $carParentTable->getAdapter();

        $size = $adapter->fetchOne(
            $adapter->select()
                ->from($carParentTable->info('name'), 'count(1)')
                ->where('parent_id = ?', $car->id)
        );

        return array(
            'id'    => 'car' . $car->id,
            'label' => $car->getFullName(),
            'size'  => $size,
            'x'     => rand(1, 100),
            'y'     => rand(1, 100),
        );
    }

    public function dataAction()
    {
        $carTable = new Cars();

        $carId = $this->_getParam('id');

        $car = $carTable->find($carId)->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $nodes = array($this->_carNode($car));
        $edges = array();

        $childCars = $carTable->fetchAll(
            $carTable->select(true)
                ->join('car_parent', 'cars.id = car_parent.car_id', null)
                ->where('car_parent.parent_id = ?', $car->id)
        );

        foreach ($childCars as $childCar) {
            $nodes[] = $this->_carNode($childCar);
            $edges[] = array(
                "id" => "e" . $car->id . 't' . $childCar->id,
                "source" => 'car' . $car->id,
                "target" => 'car' . $childCar->id
            );
        }

        $parentCars = $carTable->fetchAll(
            $carTable->select(true)
                ->join('car_parent', 'cars.id = car_parent.parent_id', null)
                ->where('car_parent.car_id = ?', $car->id)
        );

        foreach ($parentCars as $parentCar) {
            $nodes[] = $this->_carNode($parentCar);
            $edges[] = array(
                "id" => "e" . $parentCar->id . 't' . $car->id,
                "source" => 'car' . $parentCar->id,
                "target" => 'car' . $car->id
            );
        }

        return $this->_helper->json(array(
            "nodes" => $nodes,
            "edges" => $edges
        ));
    }
}