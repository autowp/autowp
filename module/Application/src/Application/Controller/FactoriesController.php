<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;

use Application\Model\DbTable;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Service\SpecificationsService;

use Zend_Db_Expr;

class FactoriesController extends AbstractActionController
{
    private $textStorage;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var Perspective
     */
    private $perspective;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    public function __construct(
        $textStorage,
        SpecificationsService $specsService,
        Perspective $perspective,
        Item $itemModel,
        DbTable\Picture $pictureTable
    ) {
        $this->textStorage = $textStorage;
        $this->specsService = $specsService;
        $this->perspective = $perspective;
        $this->itemModel = $itemModel;
        $this->pictureTable = $pictureTable;
    }

    public function indexAction()
    {
        return $this->redirect()->toUrl('/map/');
    }

    public function factoryAction()
    {
        $itemTable = $this->catalogue()->getItemTable();

        $factory = $itemTable->fetchRow([
            'id = ?'           => (int)$this->params()->fromRoute('id'),
            'item_type_id = ?' => Item::FACTORY
        ]);
        if (! $factory) {
            return $this->notFoundAction();
        }

        $select = $this->pictureTable->select(true)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->where('picture_item.item_id = ?', $factory->id)
            ->where('pictures.status = ?', Picture::STATUS_ACCEPTED);

        $pictures = $this->pic()->listData($select, [
            'width' => 4
        ]);

        $language = $this->language();
        $imageStorage = $this->imageStorage();

        $carPictures = [];
        $groups = $this->itemModel->getRelatedCarGroups($factory['id']);
        if (count($groups) > 0) {
            $cars = $itemTable->fetchAll([
                'id in (?)' => array_keys($groups)
            ], $this->catalogue()->itemOrdering());

            $catalogue = $this->catalogue();

            foreach ($cars as $car) {
                $select = $this->pictureTable->select(true)
                    ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->join('item', 'picture_item.item_id = item.id', null)
                    ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $car->id)
                    ->order([
                        new Zend_Db_Expr('item_parent_cache.tuning asc'),
                        new Zend_Db_Expr('item_parent_cache.sport asc'),
                        new Zend_Db_Expr('item.is_concept asc'),
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

                $pictureRow = $this->pictureTable->fetchRow($select);
                $src = null;
                if ($pictureRow) {
                    $request = $catalogue->getPictureFormatRequest($pictureRow->toArray());
                    $imagesInfo = $imageStorage->getFormatedImage($request, 'picture-thumb');
                    $src = $imagesInfo->getSrc();
                }

                $cataloguePaths = $catalogue->getCataloguePaths($car->id, [
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

        $point = $this->itemModel->getPoint($factory->id);

        $description = $this->itemModel->getTextOfItem($factory['id'], $this->language());

        return [
            'factory'     => $factory,
            'description' => $description,
            'pictures'    => $pictures,
            'carPictures' => $carPictures,
            'point'       => $point,
            'canEdit'     => $this->user()->isAllowed('factory', 'edit'),
            'factoryName' => $this->itemModel->getNameData($factory, $language)
        ];
    }

    public function factoryCarsAction()
    {
        $itemTable = $this->catalogue()->getItemTable();

        $factory = $itemTable->fetchRow([
            'id = ?'           => (int)$this->params()->fromRoute('id'),
            'item_type_id = ?' => Item::FACTORY
        ]);
        if (! $factory) {
            return $this->notFoundAction();
        }

        $paginator = null;

        $cars = [];
        $groups = $this->itemModel->getRelatedCarGroups($factory['id']);
        if (count($groups) > 0) {
            $select = $itemTable->select(true)
                ->where('id IN (?)', array_keys($groups))
                ->order($this->catalogue()->itemOrdering());

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
                'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                    'pictureTable'         => $this->pictureTable,
                    'perspective'          => $this->perspective,
                    'type'                 => null,
                    'onlyExactlyPictures'  => false,
                    'dateSort'             => false,
                    'disableLargePictures' => true,
                    'perspectivePageId'    => null,
                    'onlyChilds'           => $groups
                ]),
                'listBuilder' => new \Application\Model\Item\ListBuilder([
                    'catalogue'    => $this->catalogue(),
                    'router'       => $this->getEvent()->getRouter(),
                    'picHelper'    => $this->getPluginManager()->get('pic'),
                    'specsService' => $this->specsService
                ]),
            ]),
            'paginator' => $paginator
        ];
    }

    public function newcarsAction()
    {
        $itemTable = new DbTable\Item();

        $factory = $itemTable->fetchRow([
            'item_type_id = ?' => Item::FACTORY,
            'id = ?'           => (int)$this->params('item_id')
        ]);
        if (! $factory) {
            return $this->notFoundAction();
        }

        $language = $this->language();

        $rows = $itemTable->fetchAll(
            $itemTable->select(true)
                ->where('item.item_type_id IN (?)', [
                    Item::VEHICLE,
                    Item::ENGINE
                ])
                ->join('item_parent', 'item.id = item_parent.item_id', null)
                ->where('item_parent.parent_id = ?', $factory->id)
                ->where('item_parent.timestamp > DATE_SUB(NOW(), INTERVAL ? DAY)', 7)
                ->group('item.id')
                ->order(['item_parent.timestamp DESC'])
                ->limit(20)
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->itemModel->getNameData($row, $language);
        }

        $viewModel = new ViewModel([
            'items' => $items
        ]);
        $viewModel->setTerminal(true);

        return $viewModel;
    }
}
