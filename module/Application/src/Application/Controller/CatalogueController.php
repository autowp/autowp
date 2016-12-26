<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use Zend\Paginator\Paginator;

use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable\BrandLink;
use Application\Model\Brand as BrandModel;
use Application\Model\BrandVehicle;
use Application\Model\DbTable;
use Application\Model\DbTable\BrandItem;
use Application\Model\DbTable\Comment\Message as CommentMessage;
use Application\Model\DbTable\Factory;
use Application\Model\DbTable\Modification as ModificationTable;
use Application\Model\DbTable\Modification\Group as ModificationGroup;
use Application\Model\DbTable\Perspective\Group as PerspectiveGroup;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Picture\Row as PictureRow;
use Application\Model\DbTable\Vehicle;
use Application\Model\DbTable\Vehicle\Language as VehicleLanguage;
use Application\Model\DbTable\Vehicle\ParentTable as VehicleParent;
use Application\Model\DbTable\Vehicle\Type as VehicleType;
use Application\Paginator\Adapter\Zend1DbTableSelect;
use Application\Service\Mosts;
use Application\Service\SpecificationsService;
use Application\ItemNameFormatter;

use Zend_Db_Expr;
use Zend_Db_Table_Select;

class CatalogueController extends AbstractActionController
{
    private $mostsMinCarsCount = 200;

    private $textStorage;

    private $cache;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var BrandVehicle
     */
    private $brandVehicle;

    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    public function __construct(
        $textStorage,
        $cache,
        SpecificationsService $specsService,
        BrandVehicle $brandVehicle,
        ItemNameFormatter $itemNameFormatter
    ) {

        $this->textStorage = $textStorage;
        $this->cache = $cache;
        $this->specsService = $specsService;
        $this->brandVehicle = $brandVehicle;
        $this->itemNameFormatter = $itemNameFormatter;
    }

    private function doBrandAction(callable $callback)
    {
        $language = $this->language();

        $brandModel = new BrandModel();

        $brand = $brandModel->getBrandByCatname($this->params('brand_catname'), $language);

        if (! $brand) {
            return $this->notFoundAction();
        }

        $result = $callback($brand);
        if (is_array($result)) {
            $result = array_replace([
                'brand' => $brand
            ], $result);
        }

        return $result;
    }

    /**
     * @param Zend_Db_Table_Select $select
     * @param string $pictureId
     * @return PictureRow
     */
    private function fetchSelectPicture(Zend_Db_Table_Select $select, $pictureId)
    {
        $selectRow = clone $select;

        $selectRow
            ->where('pictures.id = ?', (int)$pictureId)
            ->where('pictures.identity IS NULL');

        $picture = $selectRow->getTable()->fetchRow($selectRow);

        if (! $picture) {
            $selectRow = clone $select;

            $selectRow->where('pictures.identity = ?', (string)$pictureId);

            $picture = $selectRow->getTable()->fetchRow($selectRow);
        }

        return $picture;
    }

    /**
     *
     * @return Zend_Db_Table_Select
     */
    private function selectOrderFromPictures($onlyAccepted = true)
    {
        return $this->selectFromPictures($onlyAccepted)
            ->order($this->catalogue()->picturesOrdering());
    }

    /**
     *
     * @return Zend_Db_Table_Select
     */
    private function selectFromPictures($onlyAccepted = true)
    {
        $select = $this->catalogue()->getPictureTable()->select(true);

        if ($onlyAccepted) {
            $select->where('pictures.status IN (?)', [
                Picture::STATUS_NEW, Picture::STATUS_ACCEPTED
            ]);
        }

        return $select;
    }

    /**
     * @param Zend_Db_Table_Select $select
     * @param int $page
     * @return Paginator
     */
    private function carsPaginator(Zend_Db_Table_Select $select, $page)
    {
        $paginator = new Paginator(
            new Zend1DbTableSelect($select)
        );

        return $paginator
            ->setItemCountPerPage($this->catalogue()->getCarsPerPage())
            ->setCurrentPageNumber($page);
    }

    private function carsOrder()
    {
        return $this->catalogue()->carsOrdering();
    }

    private function picturesPaginator(Zend_Db_Table_Select $select, $page)
    {
        $paginator = new Paginator(
            new Zend1DbTableSelect($select)
        );
        return $paginator
            ->setItemCountPerPage($this->catalogue()->getPicturesPerPage())
            ->setCurrentPageNumber($page);
    }

    private function getCarShortName($brand, $carName)
    {
        $shortName = $carName;
        $patterns = [
            preg_quote($brand['name'].'-', '|') => '',
            preg_quote($brand['name'], '|') => '',
            '[[:space:]]+' => ' '
        ];
        foreach ($patterns as $pattern => $replacement) {
            $shortName = preg_replace('|'.$pattern.'|isu', $replacement, $shortName);
        }

        $shortName = trim($shortName);

        return $shortName;
    }

    public function recentAction()
    {
        return $this->doBrandAction(function ($brand) {

            $select = $this->selectFromPictures()
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->join('brand_item', 'item_parent_cache.parent_id = brand_item.car_id', null)
                ->where('brand_item.brand_id = ?', $brand['id'])
                ->group('pictures.id')
                ->order([
                    'pictures.accept_datetime DESC',
                    'pictures.add_date DESC',
                    'pictures.id DESC'
                ]);

            $paginator = $this->picturesPaginator($select, $this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

            $picturesData = $this->pic()->listData($select, [
                'width' => 4
            ]);

            $this->sidebar()->brand([
                'brand_id' => $brand['id'],
            ]);

            return [
                'paginator'    => $paginator,
                'picturesData' => $picturesData,
            ];
        });
    }

    public function conceptsAction()
    {
        return $this->doBrandAction(function ($brand) {

            $select = $this->catalogue()->getCarTable()->select(true)
                ->join('item_parent_cache', 'cars.id = item_parent_cache.item_id', null)
                ->join('brand_item', 'item_parent_cache.parent_id = brand_item.car_id', null)
                ->where('brand_item.brand_id = ?', $brand['id'])
                ->where('cars.is_concept')
                ->where('not cars.is_concept_inherit')
                ->group('cars.id')
                ->order($this->carsOrder());

            $paginator = $this->carsPaginator($select, $this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            $carParentTable = new VehicleParent();

            $this->sidebar()->brand([
                'brand_id'    => $brand['id'],
                'is_concepts' => true
            ]);

            return [
                'paginator' => $paginator,
                'listData'  => $this->car()->listData($paginator->getCurrentItems(), [
                    'listBuilder' => new \Application\Model\Item\ListBuilder\Catalogue([
                        'catalogue'       => $this->catalogue(),
                        'router'          => $this->getEvent()->getRouter(),
                        'picHelper'       => $this->getPluginManager()->get('pic'),
                        'brand'           => $brand,
                        'specsService'    => $this->specsService,
                        'itemParentTable' => $carParentTable
                    ]),
                    'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                        'type'                 => null,
                        'onlyExactlyPictures'  => false,
                        'dateSort'             => false,
                        'disableLargePictures' => false,
                        'perspectivePageId'    => null,
                        'onlyChilds'           => []
                    ])
                ])
            ];
        });
    }

    public function carsAction()
    {
        return $this->doBrandAction(function ($brand) {

            $carTypeTable = new VehicleType();

            $cartype = false;
            if ($this->params('cartype_catname')) {
                $cartype = $carTypeTable->fetchRow(
                    $carTypeTable->select()
                        ->from($carTypeTable, ['id', 'name', 'catname'])
                        ->where('catname = ?', $this->params('cartype_catname'))
                );

                if (! $cartype) {
                    return $this->notFoundAction();
                }
            }

            $carTypeAdapter = $carTypeTable->getAdapter();
            $select = $carTypeAdapter->select()
                ->from($carTypeTable->info('name'), [
                    'id',
                    'cars_count' => new Zend_Db_Expr('COUNT(1)')
                ])
                ->join('vehicle_vehicle_type', 'car_types.id = vehicle_vehicle_type.vehicle_type_id', null)
                ->join('cars', 'vehicle_vehicle_type.vehicle_id = cars.id', null)
                ->join('item_parent_cache', 'cars.id = item_parent_cache.item_id', null)
                ->join('brand_item', 'item_parent_cache.parent_id = brand_item.car_id', null)
                ->where('brand_item.brand_id = ?', $brand['id'])
                ->where('cars.begin_year or cars.begin_model_year')
                ->where('not cars.is_group')
                ->group('car_types.id')
                ->order('car_types.position');

            $list = [];
            foreach ($carTypeAdapter->fetchAll($select) as $row) {
                $carType = $carTypeTable->find($row['id'])->current();
                if ($carType) {
                    $list[] = [
                        'id'        => $carType->id,
                        'name'      => $carType->name,
                        'carsCount' => $row['cars_count'],
                        'url'       => $this->url()->fromRoute('catalogue', [
                            'cartype_catname' => $carType->catname,
                            'page'            => 1
                        ], [], true)
                    ];
                }
            }

            $select = $this->catalogue()->getCarTable()->select(true)
                ->join('item_parent_cache', 'cars.id = item_parent_cache.item_id', null)
                ->join('brand_item', 'item_parent_cache.parent_id = brand_item.car_id', null)
                ->where('brand_item.brand_id = ?', $brand['id'])
                ->where('cars.begin_year or cars.begin_model_year')
                ->where('not cars.is_group')
                ->group('cars.id')
                ->order($this->carsOrder());
            if ($cartype) {
                $select
                    ->join('vehicle_vehicle_type', 'cars.id = vehicle_vehicle_type.vehicle_id', null)
                    ->where('vehicle_vehicle_type.vehicle_type_id = ?', $cartype->id);
            }

            $paginator = $this->carsPaginator($select, $this->params('page'));

            if (! $paginator->getTotalItemCount()) {
                return $this->notFoundAction();
            }

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            $carParentTable = new VehicleParent();

            $this->sidebar()->brand([
                'brand_id' => $brand['id']
            ]);

            return [
                'cartypes'  => $list,
                'cartype'   => $cartype,
                'paginator' => $paginator,
                'listData'  => $this->car()->listData($paginator->getCurrentItems(), [
                    'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                        'type'                 => null,
                        'onlyExactlyPictures'  => false,
                        'dateSort'             => false,
                        'disableLargePictures' => false,
                        'perspectivePageId'    => null,
                        'onlyChilds'           => []
                    ]),
                    'listBuilder' => new \Application\Model\Item\ListBuilder\Catalogue([
                        'catalogue'       => $this->catalogue(),
                        'router'          => $this->getEvent()->getRouter(),
                        'picHelper'       => $this->getPluginManager()->get('pic'),
                        'brand'           => $brand,
                        'specsService'    => $this->specsService,
                        'itemParentTable' => $carParentTable
                    ])
                ])
            ];
        });
    }

    private function getBrandFactories($brandId)
    {
        $factoryTable = new Factory();
        $db = $factoryTable->getAdapter();
        $rows = $db->fetchAll(
            $db->select()
                ->from(
                    'factory',
                    [
                        'factory_id'   => 'id',
                        'factory_name' => 'name',
                        'cars_count'   => 'count(item_parent_cache.item_id)'
                    ]
                )
                ->join('factory_item', 'factory.id = factory_item.factory_id', null)
                ->join('item_parent_cache', 'factory_item.item_id = item_parent_cache.item_id', null)
                ->join('brand_item', 'item_parent_cache.parent_id = brand_item.car_id', null)
                ->where('brand_item.brand_id = ?', $brandId)
                ->group('factory.id')
                ->join('pictures', 'factory.id = pictures.factory_id', null)
                ->where('pictures.type = ?', Picture::FACTORY_TYPE_ID)
                ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
                ->columns([
                    'pictures.id', 'pictures.identity',
                    'pictures.width', 'pictures.height',
                    'pictures.crop_left', 'pictures.crop_top', 'pictures.crop_width', 'pictures.crop_height',
                    'pictures.status', 'pictures.image_id'
                ])
                ->order('cars_count desc')
                ->limit(4)
        );

        // prefetch
        $requests = [];
        foreach ($rows as $idx => $picture) {
            $requests[$idx] = PictureRow::buildFormatRequest($picture);
        }

        $imagesInfo = $this->imageStorage()->getFormatedImages($requests, 'picture-thumb');

        $factories = [];
        foreach ($rows as $idx => $row) {
            $factories[] = [
                'name' => $row['factory_name'],
                'url'  => $this->url()->fromRoute('factories/factory', [
                    'id' => $row['factory_id']
                ]),
                'src'       => isset($imagesInfo[$idx]) ? $imagesInfo[$idx]->getSrc() : null
            ];
        }

        return $factories;
    }

    public function brandAction()
    {
        return $this->doBrandAction(function ($brand) {

            $language = $this->language();

            $httpsFlag = $this->getRequest()->getUri()->getScheme();

            $key = 'BRAND_'.$brand['id'].'_TOP_PICTURES_6_' . $language . '_' . $httpsFlag;
            $topPictures = $this->cache->getItem($key, $success);
            if (! $success) {
                $select = $this->selectOrderFromPictures()
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->join('brand_item', 'item_parent_cache.parent_id = brand_item.car_id', null)
                    ->where('brand_item.brand_id = ?', $brand['id'])
                    ->where('pictures.status IN (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
                    ->group('pictures.id')
                    ->limit(12);

                $carParentTable = new VehicleParent();

                $topPictures = $this->pic()->listData($select, [
                    'width' => 4,
                    'url'   => function ($picture) use ($carParentTable, $brand) {

                        $db = $carParentTable->getAdapter();

                        $carId = $db->fetchOne(
                            $db->select()
                                ->from('picture_item', 'item_id')
                                ->where('picture_item.picture_id = ?', $picture['id'])
                                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                                ->join('brand_item', 'item_parent_cache.parent_id = brand_item.car_id', null)
                                ->where('brand_item.brand_id = ?', $brand['id'])
                        );

                        if (! $carId) {
                            return $this->pic()->url($picture['id'], $picture['identity']);
                        }

                        $paths = $carParentTable->getPathsToBrand($carId, $brand['id'], [
                            'breakOnFirst' => true
                        ]);

                        if (count($paths) <= 0) {
                            return $this->pic()->url($picture['id'], $picture['identity']);
                        }

                        $path = $paths[0];

                        return $this->url()->fromRoute('catalogue', [
                            'action'        => 'brand-item-picture',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path'],
                            'picture_id'    => $picture['identity'] ? $picture['identity'] : $picture['id']
                        ]);
                    }
                ]);

                $this->cache->setItem($key, $topPictures);
            }

            $types = [
                'official' => [],
                'helper'   => [],
                'club'     => [],
                'default'  => []
            ];

            $links = new BrandLink();
            foreach ($types as $key => &$type) {
                $type['links'] = $links->fetchAll(
                    $links->select()
                        ->where('brandId = ?', $brand['id'])
                        ->where('type = ?', $key)
                );
            }
            foreach ($types as $key => &$type) {
                if (count($type['links']) <= 0) {
                    unset($types[$key]);
                }
            }

            $cars = $this->catalogue()->getCarTable();

            $haveTwins = $cars->getAdapter()->fetchOne(
                $cars->getAdapter()->select()
                    ->from($cars->info('name'), 'id')
                    ->join('item_parent_cache', 'cars.id = item_parent_cache.item_id', null)
                    ->join('brand_item', 'item_parent_cache.parent_id = brand_item.car_id', null)
                    ->join('twins_groups_cars', 'cars.id = twins_groups_cars.car_id', null)
                    ->where('brand_item.brand_id = ?', $brand['id'])
                    ->limit(1)
            );

            $description = null;
            if ($brand['text_id']) {
                $description = $this->textStorage->getText($brand['text_id']);
            }

            $this->sidebar()->brand([
                'brand_id' => $brand['id']
            ]);

            $inboxBrandPictures = null;
            $inboxVehiclePictures = null;

            if ($this->user()->isAllowed('picture', 'move')) {
                $pictureTable = new Picture();
                $db = $pictureTable->getAdapter();

                $brandRelatedTypes = [
                    Picture::MIXED_TYPE_ID,
                    Picture::UNSORTED_TYPE_ID,
                    Picture::LOGO_TYPE_ID
                ];

                $inboxBrandPictures = $db->fetchOne(
                    $db->select()
                        ->from('pictures', 'count(1)')
                        ->where('pictures.type in (?)', $brandRelatedTypes)
                        ->where('pictures.status = ?', Picture::STATUS_INBOX)
                        ->where('pictures.brand_id = ?', $brand['id'])
                );
                $inboxVehiclePictures = $db->fetchOne(
                    $db->select()
                        ->from('pictures', 'count(distinct pictures.id)')
                        ->where('pictures.status = ?', Picture::STATUS_INBOX)
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                        ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                        ->join('brand_item', 'item_parent_cache.parent_id = brand_item.car_id', null)
                        ->where('brand_item.brand_id = ?', $brand['id'])
                );
            }

            $requireAttention = 0;
            $isModerator = $this->user()->inheritsRole('moder');
            if ($isModerator) {
                $requireAttention = $this->getBrandModerAttentionCount($brand['id']);
            }

            return [
                'topPictures' => $topPictures,
                'link_types'  => $types,
                'haveTwins'   => $haveTwins,
                'mostsActive' => $this->mostsActive($brand['id']),
                'description' => $description,
                'factories'   => $this->getBrandFactories($brand['id']),
                'inboxBrandPictures'   => $inboxBrandPictures,
                'inboxVehiclePictures' => $inboxVehiclePictures,
                'requireAttention'     => $requireAttention
            ];
        });
    }

    /**
     * @param int $brandId
     * @param int $type
     * @return Zend_Db_Table_Select
     */
    private function typePicturesSelect($brandId, $type, $onlyAccepted = true)
    {
        return $this->selectOrderFromPictures($onlyAccepted)
            ->where('pictures.brand_id = ?', $brandId)
            ->where('pictures.type = ?', $type);
    }

    private function typePictures($type)
    {
        return $this->doBrandAction(function ($brand) use ($type) {

            $select = $this->typePicturesSelect($brand['id'], $type);

            $paginator = $this->picturesPaginator($select, $this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

            $picturesData = $this->pic()->listData($select, [
                'width' => 4,
                'url'   => function ($row) {
                    return $this->url()->fromRoute('catalogue', [
                        'action'     => $this->params('action') . '-picture',
                        'picture_id' => $row['identity'] ? $row['identity'] : $row['id']
                    ], [], true);
                }
            ]);

            $this->sidebar()->brand([
                'brand_id' => $brand['id'],
                'type'     => $type
            ]);

            return [
                'brand'        => $brand,
                'paginator'    => $paginator,
                'picturesData' => $picturesData
            ];
        });
    }

    public function otherAction()
    {
        return $this->typePictures(Picture::UNSORTED_TYPE_ID);
    }

    public function mixedAction()
    {
        return $this->typePictures(Picture::MIXED_TYPE_ID);
    }

    public function logotypesAction()
    {
        return $this->typePictures(Picture::LOGO_TYPE_ID);
    }

    private function typePicturesPicture($type)
    {
        return $this->doBrandAction(function ($brand) use ($type) {

            $select = $this->typePicturesSelect($brand['id'], $type, false);

            return $this->pictureAction($select, function ($select, $picture) use ($brand, $type) {

                $this->sidebar()->brand([
                    'brand_id' => $brand['id'],
                    'type'     => $type
                ]);

                return [
                    'picture'     => array_replace(
                        $this->pic()->picPageData($picture, $select),
                        [
                            'gallery2'   => true,
                            'galleryUrl' => $this->url()->fromRoute('catalogue', [
                                'action'  => str_replace('-picture', '-gallery', $this->params('action')),
                                'gallery' => $this->galleryType($picture)
                            ], [], true)
                        ]
                    )
                ];
            });
        });
    }

    public function otherPictureAction()
    {
        return $this->typePicturesPicture(Picture::UNSORTED_TYPE_ID);
    }

    public function mixedPictureAction()
    {
        return $this->typePicturesPicture(Picture::MIXED_TYPE_ID);
    }

    public function logotypesPictureAction()
    {
        return $this->typePicturesPicture(Picture::LOGO_TYPE_ID);
    }

    private function typePicturesGallery($type)
    {
        return $this->doBrandAction(function ($brand) use ($type) {

            $select = $this->typePicturesSelect($brand['id'], $type, false);

            switch ($this->params('gallery')) {
                case 'inbox':
                    $select->where('pictures.status = ?', Picture::STATUS_INBOX);
                    break;
                case 'removing':
                    $select->where('pictures.status = ?', Picture::STATUS_REMOVING);
                    break;
                default:
                    $select->where('pictures.status in (?)', [Picture::STATUS_NEW, Picture::STATUS_ACCEPTED]);
                    break;
            }

            return new JsonModel($this->pic()->gallery2($select, [
                'page'      => $this->params()->fromQuery('page'),
                'pictureId' => $this->params()->fromQuery('pictureId'),
                'reuseParams' => true,
                'urlParams' => [
                    'action' => str_replace('-gallery', '-picture', $this->params('action'))
                ]
            ]));
        });
    }

    public function otherGalleryAction()
    {
        return $this->typePicturesGallery(Picture::UNSORTED_TYPE_ID);
    }

    public function mixedGalleryAction()
    {
        return $this->typePicturesGallery(Picture::MIXED_TYPE_ID);
    }

    public function logotypesGalleryAction()
    {
        return $this->typePicturesGallery(Picture::LOGO_TYPE_ID);
    }

    private function stripName($brand, $name)
    {
        $name = explode(' ', $name);
        foreach ($name as $key => $value) {
            if ($brand['name'] == $value) {
                unset($name[$key]);
            }
        }

        return implode(' ', $name);
    }

    private function getCarNames(array $ids)
    {
        $result = [];

        if (count($ids)) {
            $carTable = $this->catalogue()->getCarTable();
            $db = $carTable->getAdapter();

            $language = $this->language();

            $rows = $db->fetchAll(
                $db->select()
                    ->from('cars', [
                        'cars.id',
                        'name' => 'if(car_language.name, car_language.name, cars.name)',
                        'cars.begin_model_year', 'cars.end_model_year',
                        'spec' => 'spec.short_name',
                        'spec_full' => 'spec.name',
                        'cars.body', 'cars.today',
                        'cars.begin_year', 'cars.end_year'
                    ])
                    ->joinLeft('car_language', 'cars.id = car_language.car_id and car_language.language = :lang', null)
                    ->joinLeft('spec', 'cars.spec_id = spec.id', null)
                    ->where('cars.id in (?)', $ids),
                [
                    'lang' => $language
                ]
            );

            foreach ($rows as $row) {
                 $result[$row['id']] = $this->itemNameFormatter->format(
                    $row,
                    $language
                );
            }
        }

        return $result;
    }

    private function doBrandItemAction(callable $callback)
    {
        return $this->doBrandAction(function ($brand) use ($callback) {

            $carTable = $this->catalogue()->getCarTable();

            $language = $this->language();

            $path = $this->params('path');
            $path = $path ? (array)$path : [];
            $path = array_values($path);

            $db = $carTable->getAdapter();
            $select = $db->select()
                ->from('cars', [])
                ->joinLeft('car_language', 'cars.id = car_language.car_id and car_language.language = :lang', null)
                ->joinLeft('spec', 'cars.spec_id = spec.id', null);

            $columns = [
                'cars.id',
                'cars.is_concept',
                'cars.item_type_id',
                'name' => 'if(length(car_language.name) > 0, car_language.name, cars.name)',
                'cars.begin_model_year', 'cars.end_model_year',
                'spec' => 'spec.short_name',
                'cars.body', 'cars.today', 'cars.produced', 'cars.produced_exactly',
                'cars.begin_year', 'cars.end_year', 'cars.begin_month', 'cars.end_month',
                'cars.is_group',
                'brand_item_catname' => 'brand_item.catname'
            ];

            $field = 'cars.id';
            foreach (array_reverse($path) as $idx => $pathNode) {
                $cpAlias = 'cp'. $idx;
                $select
                    ->join(
                        [$cpAlias => 'car_parent'],
                        $field . ' = ' . $cpAlias . '.car_id',
                        null
                    )
                    ->where($cpAlias.'.catname = ?', $pathNode);
                $field = $cpAlias . '.parent_id';

                $columns['cp_'.$idx.'_name'] = $cpAlias.'.name';
                $columns['cp_'.$idx.'_car_id'] = $cpAlias.'.car_id';
            }
            $columns['top_car_id'] = $field;

            $select
                ->columns($columns)
                ->join('brand_item', $field . ' = brand_item.car_id', null)
                ->where('brand_item.brand_id = :brand_id')
                ->where('brand_item.catname = :brand_item_catname');

            $currentCar = $db->fetchRow($select, [
                'lang'               => $language,
                'brand_id'           => (int)$brand['id'],
                'brand_item_catname' => (string)$this->params('car_catname')
            ]);

            if (! $currentCar) {
                return $this->notFoundAction();
            }

            $carFullName = $this->itemNameFormatter->format(
                $currentCar,
                $language
            );

            // prefetch car names
            $ids = [];
            if (count($path)) {
                $ids[] = $currentCar['top_car_id'];
            }
            foreach ($path as $idx => $pathNode) {
                $ridx = count($path) - $idx - 1;
                $idKey = 'cp_'.$ridx.'_car_id';
                $nameKey = 'cp_'.$ridx.'_name';

                if (! $currentCar[$nameKey]) {
                    $ids[] = $currentCar[$idKey];
                }
            }
            $carNames = $this->getCarNames($ids);


            // breadcrumbs
            $breadcrumbs = [];
            $breadcrumbsPath = [];

            $topCarName = null;
            if (count($path)) {
                if (isset($carNames[$currentCar['top_car_id']])) {
                    $topCarName = $carNames[$currentCar['top_car_id']];
                }
            } else {
                $topCarName = $carFullName;
            }

            $bvName = $this->brandVehicle->getName($brand['id'], $currentCar['top_car_id'], $language);
            if (! $bvName) {
                $bvName = $this->stripName($brand, $topCarName);
            }


            $breadcrumbs[] = [
                'name' => $bvName,
                'url'  => $this->url()->fromRoute('catalogue', [
                    'action'        => 'brand-item',
                    'brand_catname' => $brand['catname'],
                    'car_catname'   => $currentCar['brand_item_catname'],
                    'path'          => $breadcrumbsPath
                ])
            ];

            foreach ($path as $idx => $pathNode) {
                $ridx = count($path) - $idx - 1;
                $nameKey = 'cp_'.$ridx.'_name';
                $idKey = 'cp_'.$ridx.'_car_id';

                $breadcrumbName = $currentCar[$nameKey];
                if (! $breadcrumbName) {
                    $carId = $currentCar[$idKey];
                    if (isset($carNames[$carId])) {
                        $breadcrumbName = $this->stripName($brand, $carNames[$carId]);
                    }
                }

                $breadcrumbsPath[] = $pathNode;

                $breadcrumbs[] = [
                    'name' => $breadcrumbName,
                    'url'  => $this->url()->fromRoute('catalogue', [
                        'action'        => 'brand-item',
                        'brand_catname' => $brand['catname'],
                        'car_catname'   => $currentCar['brand_item_catname'],
                        'path'          => $breadcrumbsPath
                    ])
                ];
            }

            $design = false;

            // new design projects
            $designCarsRow = $db->fetchRow(
                $db->select()
                    ->from('brands', [
                        'brand_name'    => 'name',
                        'brand_catname' => 'folder'
                    ])
                    ->join('brand_item', 'brands.id = brand_item.brand_id', [
                        'brand_item_catname' => 'catname'
                    ])
                    ->where('brand_item.type = ?', BrandItem::TYPE_DESIGN)
                    ->join('item_parent_cache', 'brand_item.car_id = item_parent_cache.parent_id', 'item_id')
                    ->where('item_parent_cache.item_id = ?', $currentCar['id'])
            );
            if ($designCarsRow) {
                $design = [
                    'name' => $designCarsRow['brand_name'],
                    'url'  => $this->url()->fromRoute('catalogue', [
                        'action'        => 'brand-item',
                        'brand_catname' => $designCarsRow['brand_catname'],
                        'car_catname'   => $designCarsRow['brand_item_catname']
                    ])
                ];
            }

            $this->sidebar()->brand([
                'brand_id'    => $brand['id'],
                'car_id'      => $currentCar['top_car_id'],
                'is_concepts' => $currentCar['is_concept']
            ]);

            $result = $callback($brand, $currentCar, $currentCar['brand_item_catname'], $path, $breadcrumbs);

            if (is_array($result)) {
                $result = array_replace([
                    'design'       => $design,
                    'carFullName'  => $carFullName,
                    'carShortName' => $this->getCarShortName($brand, $carFullName),
                    'carCatname'   => $currentCar['brand_item_catname'],
                ], $result);
            }

            return $result;
        });
    }

    private function childsTypeCount($carId)
    {
        $carTable = $this->catalogue()->getCarTable();
        $db = $carTable->getAdapter();
        $select = $db->select()
            ->from('car_parent', ['type', 'count(1)'])
            ->where('parent_id = ?', $carId)
            ->group('type');

        $pairs = $db->fetchPairs($select);

        return [
            'stock'  => isset($pairs[VehicleParent::TYPE_DEFAULT]) ? $pairs[VehicleParent::TYPE_DEFAULT] : 0,
            'tuning' => isset($pairs[VehicleParent::TYPE_TUNING]) ? $pairs[VehicleParent::TYPE_TUNING] : 0,
            'sport'  => isset($pairs[VehicleParent::TYPE_SPORT]) ? $pairs[VehicleParent::TYPE_SPORT] : 0
        ];
    }

    public function brandItemAction()
    {
        return $this->doBrandItemAction(function ($brand, array $currentCar, $brandItemCatname, $path, $breadcrumbs) {

            $modification = null;
            $modId = (int)$this->params('mod');
            if ($modId) {
                $mTable = new ModificationTable();

                $modification = $mTable->find($modId)->current();
                if (! $modification) {
                    return $this->notFoundAction();
                }
            }

            $modgroupId = (int)$this->params('modgroup');
            if ($modgroupId) {
                $mgTable = new ModificationGroup();

                $modgroup = $mgTable->find($modgroupId)->current();
                if (! $modgroup) {
                    return $this->notFoundAction();
                }
            }

            if ($modgroupId) {
                return $this->brandItemModgroup(
                    $brand,
                    $currentCar,
                    $brandItemCatname,
                    $path,
                    $modgroupId,
                    $modId,
                    $breadcrumbs
                );
            }

            if ($currentCar['is_group']) {
                return $this->brandItemGroup(
                    $brand,
                    $currentCar,
                    $brandItemCatname,
                    $path,
                    $modgroupId,
                    $modId,
                    $breadcrumbs
                );
            }

            $type = $this->params('type');
            switch ($type) {
                case 'tuning':
                    $type = VehicleParent::TYPE_TUNING;
                    break;
                case 'sport':
                    $type = VehicleParent::TYPE_SPORT;
                    break;
                default:
                    $type = VehicleParent::TYPE_DEFAULT;
                    break;
            }

            $carTable = $this->catalogue()->getCarTable();
            $carParentTable = new VehicleParent();

            $currentCarId = $currentCar['id'];

            $listCars = $carTable->find($currentCarId);

            $currentPictures = [];
            $currentPicturesCount = 0;

            $canAcceptPicture = $this->user()->isAllowed('picture', 'accept');

            $inboxCount = 0;
            if ($canAcceptPicture) {
                $inboxCount = $this->getCarInboxCount($currentCarId);
            }

            $requireAttention = 0;
            $isModerator = $this->user()->inheritsRole('moder');
            if ($isModerator) {
                $requireAttention = $this->getCarModerAttentionCount($currentCarId);
            }

            $counts = $this->childsTypeCount($currentCarId);

            $texts = $this->getItemTexts($currentCar['id']);

            $currentCar['description'] = $texts['description'];
            $currentCar['text'] = $texts['text'];
            $hasHtml = (bool)$currentCar['text'];

            return [
                'car'           => $currentCar,
                'modificationGroups' => $this->brandItemModifications($currentCar['id'], $modId),
                'breadcrumbs'   => $breadcrumbs,
                'type'          => $type,
                'stockCount'    => $counts['stock'],
                'tuningCount'   => $counts['tuning'],
                'sportCount'    => $counts['sport'],
                'picturesCount' => 0,
                'hasHtml'       => $hasHtml,
                'currentPictures'      => $currentPictures,
                'currentPicturesCount' => $currentPicturesCount,
                'currentPicturesUrl'   => $this->url()->fromRoute('catalogue', [
                    'action'        => 'brand-item-pictures',
                    'brand_catname' => $brand['catname'],
                    'car_catname'   => $brandItemCatname,
                    'path'          => $path,
                    'exact'         => true
                ], [], true),
                'childListData' => $this->car()->listData($listCars, [
                    'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                        'type'                 => $type == VehicleParent::TYPE_DEFAULT ? $type : null,
                        'onlyExactlyPictures'  => true,
                        'dateSort'             => false,
                        'disableLargePictures' => false,
                        'perspectivePageId'    => null,
                        'onlyChilds'           => []
                    ]),
                    'listBuilder' => new \Application\Model\Item\ListBuilder\CatalogueItem([
                        'catalogue'        => $this->catalogue(),
                        'router'           => $this->getEvent()->getRouter(),
                        'picHelper'        => $this->getPluginManager()->get('pic'),
                        'brand'            => $brand,
                        'specsService'     => $this->specsService,
                        'itemParentTable'  => $carParentTable,
                        'brandItemCatname' => $brandItemCatname,
                        'itemId'           => $currentCarId,
                        'path'             => $path
                    ]),
                    'onlyExactlyPictures'  => true,
                    'disableDescription' => true,
                    'disableDetailsLink' => true,
                ]),
                'canAcceptPicture' => $canAcceptPicture,
                'inboxCount'       => $inboxCount,
                'requireAttention' => $requireAttention
            ];
        });
    }

    private function brandItemGroupModifications($carId, $groupId, $modificationId)
    {
        $mTable = new ModificationTable();
        $db = $mTable->getAdapter();

        $select = $mTable->select(true)
            ->join('item_parent_cache', 'modification.car_id = item_parent_cache.parent_id', null)
            ->where('item_parent_cache.item_id = ?', $carId)
            ->order('modification.name');

        if ($groupId) {
            $select->where('modification.group_id = ?', $groupId);
        } else {
            $select->where('modification.group_id IS NULL');
        }

        $modifications = [];
        foreach ($mTable->fetchAll($select) as $mRow) {
            $modifications[] = [
                'name'      => $mRow->name,
                'url'       => $this->url()->fromRoute('catalogue', [
                    'action' => 'brand-item', // -pictures
                    'mod'    => $mRow->id,
                ], [], true),
                'count'     => $db->fetchOne(
                    $db->select()
                        ->from('modification_picture', 'count(1)')
                        ->where('modification_picture.modification_id = ?', $mRow->id)
                        ->join('pictures', 'modification_picture.picture_id = pictures.id', null)
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                        ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                        ->where('item_parent_cache.parent_id = ?', $carId)
                ),
                'active' => $mRow->id == $modificationId
            ];
        }

        return $modifications;
    }

    private function brandItemModifications($carId, $modificationId)
    {
        // modifications
        $mgTable = new ModificationGroup();

        $modificationGroups = [];

        $mgRows = $mgTable->fetchAll(
            $mgTable->select(true)
                ->join('modification', 'modification_group.id = modification.group_id', null)
                ->join('item_parent_cache', 'modification.car_id = item_parent_cache.parent_id', null)
                ->where('item_parent_cache.item_id = ?', $carId)
                ->group('modification_group.id')
                ->order('modification_group.name')
        );

        foreach ($mgRows as $mgRow) {
            $modifications = $this->brandItemGroupModifications($carId, $mgRow->id, $modificationId);

            if ($modifications) {
                $modificationGroups[] = [
                    'name'          => $mgRow->name,
                    'modifications' => $modifications,
                    'url'           => $this->url()->fromRoute('catalogue', [
                        'action'   => 'brand-item',
                        'modgroup' => $mgRow->id,
                    ], [], true)
                ];
            }
        }

        $modifications = $this->brandItemGroupModifications($carId, null, $modificationId);
        if ($modifications) {
            $modificationGroups[] = [
                'name'          => null,
                'modifications' => $modifications
            ];
        }

        return $modificationGroups;
    }

    private function getModgroupPicturesSelect($carId, $modId)
    {
        $pictureTable = $this->catalogue()->getPictureTable();
        $db = $pictureTable->getAdapter();

        return $db->select()
            ->from(
                $pictureTable->info('name'),
                [
                    'id', 'name', 'type', 'brand_id', 'factory_id',
                    'image_id', 'crop_left', 'crop_top',
                    'crop_width', 'crop_height', 'width', 'height', 'identity', 'factory_id'
                ]
            )
            ->where('pictures.status IN (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
            ->where('item_parent_cache.parent_id = ?', $carId)
            ->join('modification_picture', 'pictures.id = modification_picture.picture_id', null)
            ->where('modification_picture.modification_id = ?', $modId)
            ->limit(1);
    }

    private function getModgroupPictureList($carId, $modId, array $perspectiveGroupIds)
    {
        $pictures = [];
        $usedIds = [];

        $pictureTable = $this->catalogue()->getPictureTable();
        $db = $pictureTable->getAdapter();

        foreach ($perspectiveGroupIds as $groupId) {
            $select = $this->getModgroupPicturesSelect($carId, $modId)
                ->join(
                    ['mp' => 'perspectives_groups_perspectives'],
                    'picture_item.perspective_id = mp.perspective_id',
                    null
                )
                ->where('mp.group_id = ?', $groupId)
                ->order([
                    //'cars.is_concept asc',
                    'item_parent_cache.sport asc',
                    'item_parent_cache.tuning asc',
                    'mp.position'
                ])
                ->limit(1);

            /*
            if (isset($options['type'])) {
                switch ($options['type']) {
                    case VehicleParent::TYPE_DEFAULT:
                        break;
                    case VehicleParent::TYPE_TUNING:
                        $select->where('item_parent_cache.tuning');
                        break;
                    case VehicleParent::TYPE_SPORT:
                        $select->where('item_parent_cache.sport');
                        break;
                }
            }
            */

            if ($usedIds) {
                $select
                    ->where('pictures.id not in (?)', $usedIds);
            }


            $picture = $db->fetchRow($select);

            if ($picture) {
                $pictures[] = $picture;
                $usedIds[] = (int)$picture['id'];
            } else {
                $pictures[] = null;
            }
        }

        foreach ($pictures as &$picture) {
            if (! $picture) {
                $select = $this->getModgroupPicturesSelect($carId, $modId)->limit(1)
                    ->order([
                        //'cars.is_concept asc',
                        'item_parent_cache.sport asc',
                        'item_parent_cache.tuning asc'
                    ]);
                if ($usedIds) {
                    $select->where('pictures.id not in (?)', $usedIds);
                }

                $picture = $db->fetchRow($select);
                if ($picture) {
                    $usedIds[] = $picture['id'];
                }
            }
        }
        unset($picture);

        $result = [];
        foreach ($pictures as $picture) {
            if ($picture) {
                $format = 'picture-thumb';

                $url = $this->pic()->href($picture);

                /*if ($urlCallback) {
                    $url = $urlCallback($car, $picture);
                } else {

                }*/

                $result[] = [
                    'format' => $format,
                    'row'    => $picture,
                    'url'    => $url,
                ];
            } else {
                $result[] = false;
            }
        }

        return $result;
    }

    private function getPerspectiveGroupIds($pageId)
    {
        $perspectivesGroups = new PerspectiveGroup();
        $db = $perspectivesGroups->getAdapter();
        return $db->fetchCol(
            $db->select()
                ->from($perspectivesGroups->info('name'), 'id')
                ->where('page_id = ?', $pageId)
                ->order('position')
        );
    }

    private function brandItemModgroup(
        $brand,
        array $currentCar,
        $brandItemCatname,
        $path,
        $modgroupId,
        $modId,
        $breadcrumbs
    ) {
        $currentCarId = $currentCar['id'];

        $mTable = new ModificationTable();
        $imageStorage = $this->imageStorage();
        $catalogue = $this->catalogue();

        $g = $this->getPerspectiveGroupIds(2);

        $select = $mTable->select(true)
            ->join('item_parent_cache', 'modification.car_id = item_parent_cache.parent_id', null)
            ->where('item_parent_cache.item_id = ?', $currentCarId)
            ->where('modification.group_id = ?', $modgroupId)
            ->group('modification.id')
            ->order('modification.name');

        $modifications = [];
        foreach ($mTable->fetchAll($select) as $modification) {
            $pictures = [];

            $pictureRows = $this->getModgroupPictureList($currentCarId, $modification['id'], $g);
            $select = $this->getModgroupPicturesSelect($currentCarId, $modification['id']);
            $pPaginator = new Paginator(
                new Zend1DbTableSelect($select)
            );

            foreach ($pictureRows as $pictureRow) {
                if ($pictureRow) {
                    $request = $catalogue->getPictureFormatRequest($pictureRow['row']);
                    $imageInfo = $imageStorage->getFormatedImage($request, 'picture-thumb');

                    $pictures[] = [
                        'src'  => $imageInfo ? $imageInfo->getSrc() : null,
                        'url'  => $this->url()->fromRoute('catalogue', [
                            'action'        => 'brand-item-picture',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $brandItemCatname,
                            'path'          => $path,
                            'exact'         => false,
                            'picture_id'    => $pictureRow['row']['identity']
                                ? $pictureRow['row']['identity']
                                : $pictureRow['row']['id']
                        ], [], true)
                    ];
                } else {
                    $pictures[] = false;
                }
            }

            $nameParams = [
                'spec'             => $modification['name'],
                'begin_year'       => $modification['begin_year'],
                'end_year'         => $modification['end_year'],
                'begin_month'      => $modification['begin_month'],
                'end_month'        => $modification['end_month'],
                'begin_model_year' => $modification['begin_model_year'],
                'end_model_year'   => $modification['end_model_year'],
                'today'            => $modification['today']
            ];
            foreach ($nameParams as $key => &$nameParam) {
                if (! isset($nameParam)) {
                    unset($nameParams[$key]);
                }
            }
            unset($nameParam);

            $modifications[] = [
                'nameParams' => $nameParams,
                'name'       => $modification['name'],
                'url'        => $this->url()->fromRoute('catalogue', [
                    'action' => 'brand-item-pictures',
                    'mod'    => $modification['id']
                ], [], true),
                'pictures'      => $pictures,
                'totalPictures' => $pPaginator->getTotalItemCount()
            ];
        }

        $canAcceptPicture = $this->user()->isAllowed('picture', 'accept');

        $inboxCount = 0;
        if ($canAcceptPicture) {
            $inboxCount = $this->getCarInboxCount($currentCarId);
        }

        $requireAttention = 0;
        $isModerator = $this->user()->inheritsRole('moder');
        if ($isModerator) {
            $requireAttention = $this->getCarModerAttentionCount($currentCarId);
        }

        $texts = $this->getItemTexts($currentCar['id']);

        $currentCar['description'] = $texts['description'];
        $currentCar['text'] = $texts['text'];
        $hasHtml = (bool)$currentCar['text'];

        return [
            'modificationGroups' => $this->brandItemModifications($currentCar['id'], $modId),
            'modgroup'         => true,
            'breadcrumbs'      => $breadcrumbs,
            'car'              => $currentCar,
            'modifications'    => $modifications,
            'canAcceptPicture' => $canAcceptPicture,
            'inboxCount'       => $inboxCount,
            'requireAttention' => $requireAttention,
            'hasHtml'          => $hasHtml,
            'isCarModer'       => $this->user()->inheritsRole('cars-moder')
        ];
    }

    private function getItemTexts($itemId)
    {
        $itemLanguageTable = new DbTable\Vehicle\Language();

        $db = $itemLanguageTable->getAdapter();
        $orderExpr = $db->quoteInto('language = ? desc', $this->language());
        $itemLanguageRows = $itemLanguageTable->fetchAll([
            'car_id = ?' => $itemId
        ], new \Zend_Db_Expr($orderExpr));

        $textIds = [];
        $fullTextIds = [];
        foreach ($itemLanguageRows as $itemLanguageRow) {
            if ($itemLanguageRow->text_id) {
                $textIds[] = $itemLanguageRow->text_id;
            }
            if ($itemLanguageRow->full_text_id) {
                $fullTextIds[] = $itemLanguageRow->full_text_id;
            }
        }

        $description = null;
        if ($textIds) {
            $description = $this->textStorage->getFirstText($textIds);
        }

        $text = null;
        if ($fullTextIds) {
            $text = $this->textStorage->getFirstText($fullTextIds);
        }

        return [
            'description' => $description,
            'text'        => $text
        ];
    }

    private function brandItemGroup(
        $brand,
        array $currentCar,
        $brandItemCatname,
        $path,
        $modgroupId,
        $modId,
        $breadcrumbs
    ) {
        $currentCarId = $currentCar['id'];

        $type = $this->params('type');
        switch ($type) {
            case 'tuning':
                $type = VehicleParent::TYPE_TUNING;
                break;
            case 'sport':
                $type = VehicleParent::TYPE_SPORT;
                break;
            default:
                $type = VehicleParent::TYPE_DEFAULT;
                break;
        }

        $carTable = $this->catalogue()->getCarTable();
        $carParentTable = new VehicleParent();

        $listCars = [];

        $select = $carTable->select(true)
            ->join('car_parent', 'cars.id = car_parent.car_id', null)
            ->where('car_parent.parent_id = ?', $currentCarId)
            ->where('car_parent.type = ?', $type)
            ->order($this->carsOrder());

        $paginator = new Paginator(
            new Zend1DbTableSelect($select)
        );
        $paginator
            ->setItemCountPerPage(20)
            ->setCurrentPageNumber($this->params('page'));

        $isLastPage = $paginator->getCurrentPageNumber() == $paginator->count() || ! $paginator->count();

        foreach ($paginator->getCurrentItems() as $row) {
            $listCars[] = $row;
        }

        $currentPictures = [];
        $currentPicturesCount = 0;
        if ($isLastPage && $type == VehicleParent::TYPE_DEFAULT) {
            $select = $this->selectOrderFromPictures()
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->where('picture_item.item_id = ?', $currentCarId);
            $pPaginator = new Paginator(
                new Zend1DbTableSelect($select)
            );
            $pPaginator->setItemCountPerPage(4);

            $imageStorage = $this->imageStorage();
            $language = $this->language();

            $currentPictures = [];

            foreach ($pPaginator->getCurrentItems() as $pictureRow) {
                $imageInfo = $imageStorage->getFormatedImage($pictureRow->getFormatRequest(), 'picture-thumb');

                $currentPictures[] = [
                    'name' => $this->pic()->name($pictureRow, $language),
                    'src'  => $imageInfo ? $imageInfo->getSrc() : null,
                    'url'  => $this->url()->fromRoute('catalogue', [
                        'action'        => 'brand-item-picture',
                        'brand_catname' => $brand['catname'],
                        'car_catname'   => $brandItemCatname,
                        'path'          => $path,
                        'exact'         => true,
                        'picture_id'    => $pictureRow['identity'] ? $pictureRow['identity'] : $pictureRow['id']
                    ], [], true)
                ];
            }

            $currentPicturesCount = $pPaginator->getTotalItemCount();
        }

        $canAcceptPicture = $this->user()->isAllowed('picture', 'accept');

        $inboxCount = 0;
        if ($canAcceptPicture) {
            $inboxCount = $this->getCarInboxCount($currentCarId);
        }

        $requireAttention = 0;
        $isModerator = $this->user()->inheritsRole('moder');
        if ($isModerator) {
            $requireAttention = $this->getCarModerAttentionCount($currentCarId);
        }

        $ids = [];
        foreach ($listCars as $car) {
            $ids[] = $car->id;
        }

        $hasChildSpecs = $this->specsService->hasChildSpecs($ids);

        $picturesSelect = $this->selectFromPictures()
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
            ->where('item_parent_cache.parent_id = ?', $currentCarId);

        $counts = $this->childsTypeCount($currentCarId);


        $texts = $this->getItemTexts($currentCar['id']);

        $currentCar['description'] = $texts['description'];
        $currentCar['text'] = $texts['text'];
        $hasHtml = (bool)$currentCar['text'];

        $carLangTable = new VehicleLanguage();
        $carLangRows = $carLangTable->fetchAll([
            'car_id = ?' => $currentCar['id'],
            'length(name) > 0'
        ]);
        $otherNames = [];
        foreach ($carLangRows as $carLangRow) {
            if ($currentCar['name'] != $carLangRow->name) {
                if (! in_array($carLangRow->name, $otherNames)) {
                    $otherNames[] = $carLangRow->name;
                }
            }
        }

        return [
            'car'           => $currentCar,
            'otherNames'    => $otherNames,
            'modificationGroups' => $this->brandItemModifications($currentCar['id'], $modId),
            'paginator'     => $paginator,
            'breadcrumbs'   => $breadcrumbs,
            'type'          => $type,
            'stockCount'    => $counts['stock'],
            'tuningCount'   => $counts['tuning'],
            'sportCount'    => $counts['sport'],
            'picturesCount' => $this->picturesPaginator($picturesSelect, 1)->getTotalItemCount(),
            'hasHtml'       => $hasHtml,
            'currentPictures'      => $currentPictures,
            'currentPicturesCount' => $currentPicturesCount,
            'currentPicturesUrl'   => $this->url()->fromRoute('catalogue', [
                'action'        => 'brand-item-pictures',
                'brand_catname' => $brand['catname'],
                'car_catname'   => $brandItemCatname,
                'path'          => $path,
                'exact'         => true,
                'page'          => null
            ], [], true),
            'childListData' => $this->car()->listData($listCars, [
                'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                    'type'                 => $type == VehicleParent::TYPE_DEFAULT ? $type : null,
                    'onlyExactlyPictures'  => false,
                    'dateSort'             => false,
                    'disableLargePictures' => false,
                    'perspectivePageId'    => null,
                    'onlyChilds'           => []
                ]),
                'listBuilder' => new \Application\Model\Item\ListBuilder\CatalogueGroupItem([
                    'catalogue'        => $this->catalogue(),
                    'router'           => $this->getEvent()->getRouter(),
                    'picHelper'        => $this->getPluginManager()->get('pic'),
                    'brand'            => $brand,
                    'specsService'     => $this->specsService,
                    'itemParentTable'  => $carParentTable,
                    'brandItemCatname' => $brandItemCatname,
                    'itemId'           => $currentCarId,
                    'path'             => $path,
                    'language'         => $this->language(),
                    'textStorage'      => $this->textStorage,
                    'hasChildSpecs'    => $hasChildSpecs
                ]),
                'disableDescription' => false
            ]),
            'canAcceptPicture' => $canAcceptPicture,
            'inboxCount'       => $inboxCount,
            'requireAttention' => $requireAttention
        ];
    }

    private function getBrandModerAttentionCount($brandId)
    {
        $commentTable = new CommentMessage();

        $select = $commentTable->select(true)
            ->where('comments_messages.moderator_attention = ?', CommentMessage::MODERATOR_ATTENTION_REQUIRED)
            ->where('comments_messages.type_id = ?', CommentMessage::PICTURES_TYPE_ID)
            ->join('pictures', 'comments_messages.item_id = pictures.id', null)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
            ->join('brand_item', 'item_parent_cache.parent_id = brand_item.car_id', null)
            ->where('brand_item.brand_id = ?', $brandId);

        $paginator = new Paginator(
            new Zend1DbTableSelect($select)
        );

        return $paginator->getTotalItemCount();
    }

    private function getCarModerAttentionCount($carId)
    {
        $commentTable = new CommentMessage();

        $select = $commentTable->select(true)
            ->where('comments_messages.moderator_attention = ?', CommentMessage::MODERATOR_ATTENTION_REQUIRED)
            ->where('comments_messages.type_id = ?', CommentMessage::PICTURES_TYPE_ID)
            ->join('pictures', 'comments_messages.item_id = pictures.id', null)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
            ->where('item_parent_cache.parent_id = ?', $carId);

        $paginator = new Paginator(
            new Zend1DbTableSelect($select)
        );

        return $paginator->getTotalItemCount();
    }

    private function getCarInboxCount($carId)
    {
        $pictureTable = $this->catalogue()->getPictureTable();
        $select = $pictureTable->select(true)
            ->where('pictures.status = ?', Picture::STATUS_INBOX)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
            ->where('item_parent_cache.parent_id = ?', $carId);

        $paginator = new Paginator(
            new Zend1DbTableSelect($select)
        );

        return $paginator->getTotalItemCount();
    }

    /**
     * @param int $carId
     * @param bool $exact
     * @return Zend_Db_Table_Select
     */
    private function getBrandItemPicturesSelect($carId, $exact, $onlyAccepted = true)
    {
        $select = $this->selectOrderFromPictures($onlyAccepted)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null);

        if ($exact) {
            $select
                ->where('picture_item.item_id = ?', $carId);
        } else {
            $select
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $carId);
        }

        return $select;
    }

    public function brandItemPicturesAction()
    {
        return $this->doBrandItemAction(function ($brand, array $currentCar, $brandItemCatname, $path, $breadcrumbs) {

            $exact = (bool)$this->params('exact');

            $select = $this->getBrandItemPicturesSelect($currentCar['id'], $exact);

            $modification = null;
            $modId = (int)$this->params('mod');
            if ($modId) {
                $mTable = new ModificationTable();

                $modification = $mTable->find($modId)->current();
                if (! $modification) {
                    return $this->notFoundAction();
                }

                $select
                    ->join('modification_picture', 'pictures.id = modification_picture.picture_id', null)
                    ->where('modification_picture.modification_id = ?', $modId);
            }

            $paginator = $this->picturesPaginator($select, $this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

            $picturesData = $this->pic()->listData($select, [
                'width' => 4,
                'url'   => function ($row) use ($brand, $brandItemCatname, $path, $exact) {
                    return $this->url()->fromRoute('catalogue', [
                        'action'        => 'brand-item-picture',
                        'brand_catname' => $brand['catname'],
                        'car_catname'   => $brandItemCatname,
                        'path'          => $path,
                        'exact'         => $exact,
                        'picture_id'    => $row['identity'] ? $row['identity'] : $row['id']
                    ], [], true);
                }
            ]);

            $counts = $this->childsTypeCount($currentCar['id']);

            return [
                'breadcrumbs'   => $breadcrumbs,
                'picturesData'  => $picturesData,
                'paginator'     => $paginator,
                'stockCount'    => $counts['stock'],
                'tuningCount'   => $counts['tuning'],
                'sportCount'    => $counts['sport'],
                'picturesCount' => $paginator->getTotalItemCount(),
                'type'          => null,
                'modification'  => $modification,
                'modificationGroups' => $this->brandItemModifications($currentCar['id'], $modId),
            ];
        });
    }

    private function pictureAction($select, callable $callback)
    {
        $pictureId = (string)$this->params('picture_id');

        $picture = $this->fetchSelectPicture($select, $pictureId);
        if (! $picture) {
            return $this->notFoundAction();
        }

        $isModer = $this->user()->inheritsRole('moder');

        if ($picture->status == Picture::STATUS_REMOVING) {
            $user = $this->user()->get();
            if (! $user) {
                return $this->notFoundAction();
            }

            if ($isModer || ($user->id == $picture->owner_id)) {
                //$this->getResponse()->setStatusCode(404);
            } else {
                return $this->notFoundAction();
            }

            $select->where('pictures.status = ?', Picture::STATUS_REMOVING);
        } elseif ($picture->status == Picture::STATUS_INBOX) {
            $select->where('pictures.status = ?', Picture::STATUS_INBOX);
        } else {
            $select->where('pictures.status IN (?)', [
                Picture::STATUS_NEW, Picture::STATUS_ACCEPTED
            ]);
        }

        return $callback($select, $picture);
    }

    private function galleryType($picture)
    {
        if ($picture->status == Picture::STATUS_REMOVING) {
            $gallery = 'removing';
        } elseif ($picture->status == Picture::STATUS_INBOX) {
            $gallery = 'inbox';
        } else {
            $gallery = null;
        }

        return $gallery;
    }

    public function brandItemPictureAction()
    {
        return $this->doBrandItemAction(function ($brand, array $currentCar, $brandItemCatname, $path, $breadcrumbs) {
            $exact = (bool)$this->params('exact');

            $select = $this->getBrandItemPicturesSelect($currentCar['id'], $exact, false);

            return $this->pictureAction($select, function ($select, $picture) use ($breadcrumbs) {
                return [
                    'breadcrumbs' => $breadcrumbs,
                    'picture'     => array_replace(
                        $this->pic()->picPageData($picture, $select),
                        [
                            'gallery2'   => true,
                            'galleryUrl' => $this->url()->fromRoute('catalogue', [
                                'action'  => 'brand-item-gallery',
                                'gallery' => $this->galleryType($picture)
                            ], [], true)
                        ]
                    )
                ];
            });
        });
    }

    public function brandItemGalleryAction()
    {
        return $this->doBrandItemAction(function ($brand, array $currentCar, $brandItemCatname, $path, $breadcrumbs) {

            $exact = (bool)$this->params('exact');
            $select = $this->getBrandItemPicturesSelect($currentCar['id'], $exact, false);

            switch ($this->params('gallery')) {
                case 'inbox':
                    $select->where('pictures.status = ?', Picture::STATUS_INBOX);
                    break;
                case 'removing':
                    $select->where('pictures.status = ?', Picture::STATUS_REMOVING);
                    break;
                default:
                    $select->where('pictures.status in (?)', [Picture::STATUS_NEW, Picture::STATUS_ACCEPTED]);
                    break;
            }

            return new JsonModel($this->pic()->gallery2($select, [
                'page'      => $this->params()->fromQuery('page'),
                'pictureId' => $this->params()->fromQuery('pictureId'),
                'reuseParams' => true,
                'urlParams' => [
                    'action' => 'brand-item-picture'
                ]
            ]));
        });
    }

    public function brandItemSpecificationsAction()
    {
        return $this->doBrandItemAction(function ($brand, array $currentCar, $brandItemCatname, $path, $breadcrumbs) {

            $currentCarId = $currentCar['id'];

            $type = $this->params('type');
            switch ($type) {
                case 'tuning':
                    $type = VehicleParent::TYPE_TUNING;
                    break;
                case 'sport':
                    $type = VehicleParent::TYPE_SPORT;
                    break;
                default:
                    $type = VehicleParent::TYPE_DEFAULT;
                    break;
            }

            //$list = $this->catalogue()->getCarTable()->find($brandItemRow->car_id);

            $carTable = $this->catalogue()->getCarTable();

            $select = $carTable->select(true)
                ->order($this->carsOrder());
            if ($currentCar['is_group']) {
                $select
                    ->where('car_parent.type = ?', $type)
                    ->join('car_parent', 'cars.id = car_parent.car_id', null)
                    ->where('car_parent.parent_id = ?', $currentCarId);
            } else {
                $select
                    ->join('item_parent_cache', 'cars.id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $currentCarId)
                    ->where('item_parent_cache.diff <= 1');
            }
            $childCars = $carTable->fetchAll($select);

            if (count($childCars) <= 0) {
                $select = $carTable->select(true)
                    ->order($this->carsOrder());
                if ($currentCar['is_group']) {
                    $select
                        ->where('car_parent.type = ?', $type)
                        ->join('car_parent', 'cars.id = car_parent.car_id', null)
                        ->where('car_parent.parent_id = ?', $currentCarId);
                } else {
                    $select
                        ->join('item_parent_cache', 'cars.id = item_parent_cache.item_id', null)
                        ->where('item_parent_cache.parent_id = ?', $currentCarId);
                }

                $childCars = $carTable->fetchAll($select);
            }

            $cars = [];
            foreach ($childCars as $childCar) {
                if ($this->specsService->hasSpecs($childCar->id)) {
                    $cars[] = $childCar;
                }
            }

            $specs = $this->specsService->specifications($cars, [
                'language'     => $this->language(),
                'contextCarId' => $currentCarId
            ]);

            $ids = [];
            foreach ($cars as $car) {
                $ids[] = $car->id;
            }

            $contribPairs = $this->specsService->getContributors($ids);

            $userTable = new User();
            $contributors = $userTable->find(array_keys($contribPairs));

            return [
                'breadcrumbs'  => $breadcrumbs,
                'specs'        => $specs,
                'contributors' => $contributors
            ];
        });
    }

    private function mostsActive($brandId)
    {
        $carTable = new Vehicle();
        $db = $carTable->getAdapter();
        $carsCount = $db->fetchOne(
            $db->select()
                ->from($carTable->info('name'), 'count(1)')
                ->join('item_parent_cache', 'cars.id = item_parent_cache.item_id', null)
                ->join('brand_item', 'item_parent_cache.parent_id = brand_item.car_id', null)
                ->where('brand_item.brand_id = ?', (int)$brandId)
        );

        return $carsCount >= $this->mostsMinCarsCount;
    }

    public function brandMostsAction()
    {
        return $this->doBrandAction(function ($brand) {

            if (! $this->mostsActive($brand['id'])) {
                return $this->notFoundAction();
            }

            $service = new Mosts([
                'specs' => $this->specsService
            ]);

            $language = $this->language();
            $yearsCatname = $this->params('years_catname');
            $carTypeCatname = $this->params('shape_catname');
            $mostCatname = $this->params('most_catname');

            $data = $service->getData([
                'language' => $language,
                'most'     => $mostCatname,
                'years'    => $yearsCatname,
                'carType'  => $carTypeCatname,
                'brandId'  => $brand['id']
            ]);

            foreach ($data['sidebar']['mosts'] as &$most) {
                $most['url'] = $this->url()->fromRoute(
                    'catalogue',
                    array_merge(
                        $most['params'],
                        ['brand_catname' => $brand['catname']]
                    ),
                    [],
                    true
                );
            }
            foreach ($data['sidebar']['carTypes'] as &$carType) {
                $carType['url'] = $this->url()->fromRoute(
                    'catalogue',
                    array_merge(
                        $carType['params'],
                        ['brand_catname' => $brand['catname']]
                    ),
                    [],
                    true
                );
                foreach ($carType['childs'] as &$child) {
                    $child['url'] = $this->url()->fromRoute(
                        'catalogue',
                        array_merge(
                            $child['params'],
                            ['brand_catname' => $brand['catname']]
                        ),
                        [],
                        true
                    );
                }
            }
            foreach ($data['years'] as &$year) {
                $year['url'] = $this->url()->fromRoute(
                    'catalogue',
                    array_merge(
                        $year['params'],
                        ['brand_catname' => $brand['catname']]
                    ),
                    [],
                    true
                );
            }

            // images
            $formatRequests = [];
            $allPictures = [];
            $idx = 0;
            foreach ($data['carList']['cars'] as $car) {
                foreach ($car['pictures'] as $picture) {
                    if ($picture) {
                        $formatRequests[$idx++] = $picture->getFormatRequest();
                        $allPictures[] = $picture->toArray();
                    }
                }
            }

            $imageStorage = $this->imageStorage();
            $imagesInfo = $imageStorage->getFormatedImages($formatRequests, 'picture-thumb');

            $pictureTable = new Picture();
            $names = $pictureTable->getNameData($allPictures, [
                'language' => $language
            ]);

            $carParentTable = new VehicleParent();

            $idx = 0;
            foreach ($data['carList']['cars'] as &$car) {
                $pictures = [];

                $paths = $carParentTable->getPaths($car['car']['id'], [
                    'breakOnFirst' => true
                ]);

                foreach ($car['pictures'] as $picture) {
                    if ($picture) {
                        $id = $picture->id;

                        $url = null;
                        foreach ($paths as $path) {
                            $url = $this->url()->fromRoute('catalogue', [
                                'action'        => 'brand-item-picture',
                                'brand_catname' => $path['brand_catname'],
                                'car_catname'   => $path['car_catname'],
                                'path'          => $path['path'],
                                'picture_id'    => $picture['identity'] ? $picture['identity'] : $picture['id']
                            ]);
                        }

                        $pictures[] = [
                            'name' => isset($names[$id]) ? $names[$id] : null,
                            'src'  => isset($imagesInfo[$idx]) ? $imagesInfo[$idx]->getSrc() : null,
                            'url'  => $url
                        ];
                        $idx++;
                    } else {
                        $pictures[] = null;
                    }
                }

                $car['name'] = $car['car']->getNameData($language);
                $car['pictures'] = $pictures;
            }
            unset($car);

            $sideBarModel = new ViewModel($data);
            $sideBarModel->setTemplate('application/mosts/sidebar');
            $this->layout()->addChild($sideBarModel, 'sidebar');

            return $data;
        });
    }
}
