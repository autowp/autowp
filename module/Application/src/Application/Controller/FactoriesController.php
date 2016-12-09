<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable\Factory;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Vehicle;
use Application\Model\DbTable\Vehicle\ParentTable as VehicleParent;

use geoPHP;

use Zend_Db_Expr;

use Application\Paginator\Adapter\Zend1DbTableSelect;

class FactoriesController extends AbstractActionController
{
    private $textStorage;

    public function __construct($textStorage)
    {
        $this->textStorage = $textStorage;
    }

    public function indexAction()
    {
        return $this->redirect()->toUrl('/map/');
    }

    public function factoryAction()
    {
        $table = new Factory();

        $factory = $table->find($this->params()->fromRoute('id'))->current();
        if (! $factory) {
            return $this->notFoundAction();
        }

        $pictureTable = new Picture();

        $select = $pictureTable->select(true)
            ->where('type = ?', Picture::FACTORY_TYPE_ID)
            ->where('factory_id = ?', $factory->id)
            ->where('status = ?', Picture::STATUS_ACCEPTED);

        $pictures = $this->pic()->listData($select, [
            'width' => 4
        ]);

        $language = $this->language();
        $imageStorage = $this->imageStorage();

        $carPictures = [];
        $groups = $factory->getRelatedCarGroups();
        if (count($groups) > 0) {
            $carTable = new Vehicle();

            $cars = $carTable->fetchAll([
                'id in (?)' => array_keys($groups)
            ], $this->catalogue()->carsOrdering());

            $catalogue = $this->catalogue();
            $carParentTable = new VehicleParent();

            foreach ($cars as $car) {
                $select = $pictureTable->select(true)
                    ->where('pictures.status IN (?)', [Picture::STATUS_NEW, Picture::STATUS_ACCEPTED])
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->join('cars', 'picture_item.item_id = cars.id', null)
                    ->join('item_parent_cache', 'cars.id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $car->id)
                    ->order([
                        new Zend_Db_Expr('item_parent_cache.tuning asc'),
                        new Zend_Db_Expr('item_parent_cache.sport asc'),
                        new Zend_Db_Expr('cars.is_concept asc'),
                        new Zend_Db_Expr('picture_item.perspective_id = 10 desc'),
                        new Zend_Db_Expr('picture_item.perspective_id = 1 desc'),
                        new Zend_Db_Expr('picture_item.perspective_id = 7 desc'),
                        new Zend_Db_Expr('picture_item.perspective_id = 8 desc')
                    ]);

                if (count($groups[$car->id]) > 1) {
                    $select
                        ->join(
                            ['cpc_oc' => 'item_parent_cache'],
                            'cpc_oc.item_id = picture_item.item_id',
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

                $cataloguePaths = $carParentTable->getPaths($car->id, [
                    'breakOnFirst' => true
                ]);

                $url = null;
                foreach ($cataloguePaths as $cataloguePath) {
                    $url = $this->url()->fromRoute('catalogue', [
                        'controller'    => 'catalogue',
                        'action'        => 'brand-item',
                        'brand_catname' => $cataloguePath['brand_catname'],
                        'car_catname'   => $cataloguePath['car_catname'],
                        'path'          => $cataloguePath['path']
                    ]);
                }

                $carPictures[] = [
                    'name' => $this->car()->formatName($car, $language),
                    'src'  => $src,
                    'url'  => $url
                ];
            }
        }

        /*$carPictures = $this->pic()->listData($carPictures, [
            'width'            => 4,
            'disableBehaviour' => true
        ]);*/

        $point = null;
        if ($factory->point) {
            $point = geoPHP::load(substr($factory->point, 4), 'wkb');
        }

        $description = null;
        if ($factory['text_id']) {
            $description = $this->textStorage->getText($factory['text_id']);
        }

        return [
            'factory'     => $factory,
            'description' => $description,
            'pictures'    => $pictures,
            'carPictures' => $carPictures,
            'point'       => $point,
            'canEdit'     => $this->user()->isAllowed('factory', 'edit')
        ];
    }

    public function factoryCarsAction()
    {
        $table = new Factory();

        $factory = $table->find($this->params()->fromRoute('id'))->current();
        if (! $factory) {
            return $this->_forward('notfound', 'error');
        }

        $paginator = null;

        $cars = [];
        $groups = $factory->getRelatedCarGroups();
        if (count($groups) > 0) {
            $carTable = $this->catalogue()->getCarTable();

            $select = $carTable->select(true)
                ->where('id IN (?)', array_keys($groups))
                ->order($this->catalogue()->carsOrdering());

            $paginator = new \Zend\Paginator\Paginator(
                new Zend1DbTableSelect($select)
            );

            $paginator
                ->setItemCountPerPage($this->catalogue()->getCarsPerPage())
                ->setCurrentPageNumber($this->params()->fromRoute('page'));

            $cars = $paginator->getCurrentItems();
        }

        return [
            'factory'  => $factory,
            'carsData' => $this->car()->listData($cars, [
                'disableLargePictures' => true,
                'onlyChilds'           => $groups
            ]),
            'paginator' => $paginator
        ];
    }
}
