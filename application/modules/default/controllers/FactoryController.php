<?php

require_once APPLICATION_PATH . '/../vendor/phayes/geoPHP/geoPHP.inc';

class FactoryController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $this->_redirect('/map/');
    }

    public function factoryAction()
    {
        $table = new Factory();

        $factory = $table->find($this->_getParam('id'))->current();
        if (!$factory) {
            return $this->_forward('notfound', 'error');
        }

        $pictureTable = new Picture();

        $pictures = $pictureTable->fetchAll(array(
            'type = ?'       => Picture::FACTORY_TYPE_ID,
            'factory_id = ?' => $factory->id
        ));

        $carPictures = array();
        $groups = $factory->getRelatedCarGroups();
        if (count($groups) > 0) {
            $carTable = $this->_helper->catalogue()->getCarTable();

            $cars = $carTable->fetchAll(array(
                'id in (?)' => array_keys($groups)
            ), $this->_helper->catalogue()->carsOrdering());

            foreach ($cars as $car) {

                $select = $pictureTable->select(true)
                    ->where('pictures.status IN (?)', array(Picture::STATUS_NEW, Picture::STATUS_ACCEPTED))
                    ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                    ->join('cars', 'pictures.car_id = cars.id', null)
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                    ->where('car_parent_cache.parent_id = ?', $car->id)
                    ->order(array(
                        new Zend_Db_Expr('car_parent_cache.tuning asc'),
                        new Zend_Db_Expr('car_parent_cache.sport asc'),
                        new Zend_Db_Expr('cars.is_concept asc'),
                        new Zend_Db_Expr('pictures.perspective_id = 10 desc'),
                        new Zend_Db_Expr('pictures.perspective_id = 1 desc'),
                        new Zend_Db_Expr('pictures.perspective_id = 7 desc'),
                        new Zend_Db_Expr('pictures.perspective_id = 8 desc')
                    ));

                if (count($groups[$car->id]) > 1) {
                    $select
                        ->join(
                            array('cpc_oc' => 'car_parent_cache'),
                            'cpc_oc.car_id = pictures.car_id',
                            null
                        )
                        ->where('cpc_oc.parent_id IN (?)', $groups[$car->id]);
                }

                $carPictures[] = $pictureTable->fetchRow($select);
            }
        }

        $point = null;
        if ($factory->point) {
            $point = geoPHP::load(substr($factory->point, 4), 'wkb');
        }

        $this->view->assign(array(
            'factory'     => $factory,
            'pictures'    => $pictures,
            'carPictures' => $carPictures,
            'point'       => $point
        ));
    }

    public function factoryCarsAction()
    {
        $table = new Factory();

        $factory = $table->find($this->_getParam('id'))->current();
        if (!$factory) {
            return $this->_forward('notfound', 'error');
        }

        $paginator = null;

        $cars = array();
        $groups = $factory->getRelatedCarGroups();
        if (count($groups) > 0) {
            $carTable = $this->_helper->catalogue()->getCarTable();

            $select = $carTable->select(true)
                ->where('id IN (?)', array_keys($groups))
                ->order($this->_helper->catalogue()->carsOrdering());

            $paginator = Zend_Paginator::factory($select)
                ->setItemCountPerPage($this->_helper->catalogue()->getCarsPerPage())
                ->setCurrentPageNumber($this->_getParam('page'));

            $cars = $paginator->getCurrentItems();
        }

        $this->view->assign(array(
            'factory'  => $factory,
            'carsData' => $this->_helper->car->listData($cars, array(
                'disableLargePictures' => true,
                'onlyChilds'           => $groups
            )),
            'paginator' => $paginator
        ));
    }
}