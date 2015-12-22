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

        $select = $pictureTable->select(true)
            ->where('type = ?', Picture::FACTORY_TYPE_ID)
            ->where('factory_id = ?', $factory->id);

        $pictures = $this->_helper->pic->listData($select, array(
            'width' => 4
        ));

        $language = $this->_helper->language();
        $imageStorage = $this->getInvokeArg('bootstrap')->getResource('imagestorage');

        $carPictures = array();
        $groups = $factory->getRelatedCarGroups();
        if (count($groups) > 0) {
            $carTable = $this->_helper->catalogue()->getCarTable();

            $cars = $carTable->fetchAll(array(
                'id in (?)' => array_keys($groups)
            ), $this->_helper->catalogue()->carsOrdering());

            $catalogue = $this->_helper->catalogue();
            $carParentTable = new Car_Parent();

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

                $pictureRow = $pictureTable->fetchRow($select);
                $src = null;
                if ($pictureRow) {
                    $request = $catalogue->getPictureFormatRequest($pictureRow->toArray());
                    $imagesInfo = $imageStorage->getFormatedImage($request, 'picture-thumb');
                    $src = $imagesInfo->getSrc();
                }

                $cataloguePaths = $carParentTable->getPaths($car->id, array(
                    'breakOnFirst' => true
                ));

                $url = null;
                foreach ($cataloguePaths as $cataloguePath) {
                    $url = $this->_helper->url->url(array(
                        'module'        => 'default',
                        'controller'    => 'catalogue',
                        'action'        => 'brand-car',
                        'brand_catname' => $cataloguePath['brand_catname'],
                        'car_catname'   => $cataloguePath['car_catname'],
                        'path'          => $cataloguePath['path']
                    ), 'catalogue', true);
                }

                $carPictures[] = array(
                    'name' => $car->getFullName($language),
                    'src'  => $src,
                    'url'  => $url
                );
            }
        }

        /*$carPictures = $this->_helper->pic->listData($carPictures, array(
            'width'            => 4,
            'disableBehaviour' => true
        ));*/

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