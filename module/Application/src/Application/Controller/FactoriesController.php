<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Service\SpecificationsService;

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
     * @var Picture
     */
    private $picture;

    public function __construct(
        $textStorage,
        SpecificationsService $specsService,
        Perspective $perspective,
        Item $itemModel,
        Picture $picture
    ) {
        $this->textStorage = $textStorage;
        $this->specsService = $specsService;
        $this->perspective = $perspective;
        $this->itemModel = $itemModel;
        $this->picture = $picture;
    }

    public function indexAction()
    {
        return $this->redirect()->toUrl('/map/');
    }

    public function factoryAction()
    {
        $factory = $this->itemModel->getRow([
            'id'           => (int)$this->params()->fromRoute('id'),
            'item_type_id' => Item::FACTORY
        ]);
        if (! $factory) {
            return $this->notFoundAction();
        }

        $rows = $this->picture->getRows([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => $factory['id']
        ]);

        $pictures = $this->pic()->listData($rows, [
            'width' => 4
        ]);

        $language = $this->language();
        $imageStorage = $this->imageStorage();

        $carPictures = [];
        $groups = $this->itemModel->getRelatedCarGroups($factory['id']);
        if (count($groups) > 0) {
            $cars = $this->itemModel->getRows([
                'id'    => array_keys($groups),
                'order' => $this->catalogue()->itemOrdering()
            ]);

            $catalogue = $this->catalogue();

            foreach ($cars as $car) {
                $ancestor = count($groups[$car['id']]) > 1
                    ? $groups[$car['id']]
                    : $car['id'];

                $pictureRow = $this->picture->getRow([
                    'status' => Picture::STATUS_ACCEPTED,
                    'item'   => [
                        'ancestor_or_self' => $ancestor
                    ],
                    'order'  => 'ancestor_stock_front_first'
                ]);

                $src = null;
                if ($pictureRow) {
                    $request = $catalogue->getPictureFormatRequest($pictureRow);
                    $imagesInfo = $imageStorage->getFormatedImage($request, 'picture-thumb');
                    $src = $imagesInfo->getSrc();
                }

                $cataloguePaths = $catalogue->getCataloguePaths($car['id'], [
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

        $point = $this->itemModel->getPoint($factory['id']);

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
        $factory = $this->itemModel->getRow([
            'id'           => (int)$this->params()->fromRoute('id'),
            'item_type_id' => Item::FACTORY
        ]);
        if (! $factory) {
            return $this->notFoundAction();
        }

        $paginator = null;

        $cars = [];
        $groups = $this->itemModel->getRelatedCarGroups($factory['id']);
        if (count($groups) > 0) {
            $paginator = $this->itemModel->getPaginator([
                'id'    => array_keys($groups),
                'order' => $this->catalogue()->itemOrdering()
            ]);

            $paginator
                ->setItemCountPerPage($this->catalogue()->getCarsPerPage())
                ->setCurrentPageNumber($this->params()->fromRoute('page'));

            $cars = $paginator->getCurrentItems();
        }

        return [
            'factory'  => $factory,
            'carsData' => $this->car()->listData($cars, [
                'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                    'pictureTable'         => $this->picture->getPictureTable(),
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
        $factory = $this->itemModel->getRow([
            'item_type_id' => Item::FACTORY,
            'id'           => (int)$this->params('item_id')
        ]);
        if (! $factory) {
            return $this->notFoundAction();
        }

        $language = $this->language();

        $rows = $this->itemModel->getRows([
            'item_type_id' => [
                Item::VEHICLE,
                Item::ENGINE
            ],
            'parent' => [
                'id'             => $factory['id'],
                'linked_in_days' => 7,
            ],
            'order' => 'item_parent.timestamp DESC',
            'limit' => 20
        ]);

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
