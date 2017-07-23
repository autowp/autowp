<?php

namespace Application\Controller;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use Zend\Paginator\Paginator;

use Autowp\Comments;
use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;
use Autowp\User\Model\DbTable\User;

use Application\ItemNameFormatter;
use Application\Model\Brand as BrandModel;
use Application\Model\ItemParent;
use Application\Model\DbTable;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\VehicleType;
use Application\Service\Mosts;
use Application\Service\SpecificationsService;

use Zend_Db_Expr;
use Zend_Db_Table_Select;

class CatalogueController extends AbstractActionController
{
    private $mostsMinCarsCount = 1;

    private $textStorage;

    private $cache;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var ItemParent
     */
    private $itemParent;

    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    /**
     * @var Comments\CommentsService
     */
    private $comments;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var Perspective
     */
    private $perspective;

    /**
     * @var TableGateway
     */
    private $itemLinkTable;

    /**
     * @var Mosts
     */
    private $mosts;

    /**
     * @var VehicleType
     */
    private $vehicleType;

    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    public function __construct(
        $textStorage,
        $cache,
        SpecificationsService $specsService,
        ItemParent $itemParent,
        ItemNameFormatter $itemNameFormatter,
        $mostsMinCarsCount,
        Comments\CommentsService $comments,
        Item $itemModel,
        Perspective $perspective,
        TableGateway $itemLinkTable,
        Mosts $mosts,
        VehicleType $vehicleType,
        DbTable\Picture $pictureTable
    ) {

        $this->textStorage = $textStorage;
        $this->cache = $cache;
        $this->specsService = $specsService;
        $this->itemParent = $itemParent;
        $this->itemNameFormatter = $itemNameFormatter;
        $this->mostsMinCarsCount = $mostsMinCarsCount;
        $this->comments = $comments;
        $this->itemModel = $itemModel;
        $this->perspective = $perspective;
        $this->itemLinkTable = $itemLinkTable;
        $this->mosts = $mosts;
        $this->vehicleType = $vehicleType;
        $this->pictureTable = $pictureTable;
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
     * @return DbTable\Picture\Row
     */
    private function fetchSelectPicture(Zend_Db_Table_Select $select, $pictureId)
    {
        $selectRow = clone $select;

        $selectRow->where('pictures.identity = ?', (string)$pictureId);

        return $selectRow->getTable()->fetchRow($selectRow);
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
        $select = $this->pictureTable->select(true);

        if ($onlyAccepted) {
            $select->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED);
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
        return $this->catalogue()->itemOrdering();
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
                ->where('item_parent_cache.parent_id = ?', $brand['id'])
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

            $select = $this->catalogue()->getItemTable()->select(true)
                ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $brand['id'])
                ->where('item.is_concept')
                ->where('not item.is_concept_inherit')
                ->group('item.id')
                ->order($this->carsOrder());

            $paginator = $this->carsPaginator($select, $this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

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
                        'specsService'    => $this->specsService
                    ]),
                    'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                        'pictureTable'         => $this->pictureTable,
                        'perspective'          => $this->perspective,
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

            $cartype = false;
            $catname = $this->params('cartype_catname');
            if ($catname) {
                $cartype = $this->vehicleType->getRowByCatname($catname);
                if (! $cartype) {
                    return $this->notFoundAction();
                }
            }

            $list = $this->vehicleType->getBrandVehicleTypes($brand['id']);
            foreach ($list as &$listItem) {
                $listItem['url'] = $this->url()->fromRoute('catalogue', [
                    'cartype_catname' => $listItem['catname'],
                    'page'            => 1
                ], [], true);
            }
            unset($listItem);

            $select = $this->catalogue()->getItemTable()->select(true)
                ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $brand['id'])
                ->where('item.begin_year or item.begin_model_year')
                ->where('not item.is_group')
                ->group('item.id')
                ->order($this->carsOrder());
            if ($cartype) {
                $select
                    ->join('vehicle_vehicle_type', 'item.id = vehicle_vehicle_type.vehicle_id', null)
                    ->where('vehicle_vehicle_type.vehicle_type_id = ?', $cartype['id']);
            }

            $paginator = $this->carsPaginator($select, $this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            $this->sidebar()->brand([
                'brand_id' => $brand['id']
            ]);

            return [
                'cartypes'  => $list,
                'cartype'   => $cartype,
                'paginator' => $paginator,
                'listData'  => $this->car()->listData($paginator->getCurrentItems(), [
                    'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                        'pictureTable'         => $this->pictureTable,
                        'perspective'          => $this->perspective,
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
                        'specsService'    => $this->specsService
                    ])
                ])
            ];
        });
    }

    private function getBrandFactories($brandId)
    {
        $itemTable = new DbTable\Item();
        $db = $itemTable->getAdapter();
        $rows = $db->fetchAll(
            $db->select()
                ->from(
                    'item',
                    [
                        'factory_id'   => 'id',
                        'factory_name' => 'name',
                        'cars_count'   => 'count(ipc2.item_id)'
                    ]
                )
                ->where('item.item_type_id = ?', Item::FACTORY)
                ->join(['ipc1' => 'item_parent_cache'], 'item.id = ipc1.parent_id', [])
                ->join(['ipc2' => 'item_parent_cache'], 'ipc1.item_id = ipc2.item_id', [])
                ->where('ipc2.parent_id = ?', $brandId)
                ->group('item.id')
                ->join('picture_item', 'item.id = picture_item.item_id', [])
                ->join('pictures', 'picture_item.picture_id = pictures.id', [])
                ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
                ->order('cars_count desc')
                ->limit(4)
        );

        // prefetch
        $requests = [];
        foreach ($rows as $idx => $picture) {
            $pictureRow = $this->pictureTable->fetchRow(
                $this->pictureTable->select(true)
                    ->columns([
                        'pictures.id', 'pictures.identity',
                        'pictures.width', 'pictures.height',
                        'pictures.crop_left', 'pictures.crop_top',
                        'pictures.crop_width', 'pictures.crop_height',
                        'pictures.status', 'pictures.image_id'
                    ])
                    ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
                    ->join('picture_item', 'picture_item.picture_id = pictures.id', [])
                    ->where('picture_item.item_id = ?', $picture['factory_id'])
                    ->limit(1)
            );

            if ($pictureRow) {
                $requests[$idx] = DbTable\Picture\Row::buildFormatRequest($pictureRow->toArray());
            }
        }

        $imagesInfo = $this->imageStorage()->getFormatedImages($requests, 'picture-thumb');

        $factories = [];
        foreach ($rows as $idx => $row) {
            $factories[] = [
                'name' => $row['factory_name'],
                'url'  => $this->url()->fromRoute('factories/factory', [
                    'id' => $row['factory_id']
                ]),
                'src'  => isset($imagesInfo[$idx]) ? $imagesInfo[$idx]->getSrc() : null
            ];
        }

        return $factories;
    }

    public function brandAction()
    {
        return $this->doBrandAction(function ($brand) {

            $language = $this->language();

            $httpsFlag = $this->getRequest()->getUri()->getScheme();

            $key = 'BRAND_'.$brand['id'].'_TOP_PICTURES_7_' . $language . '_' . $httpsFlag;
            $topPictures = $this->cache->getItem($key, $success);
            if (! $success) {
                $select = $this->selectFromPictures(true)
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $brand['id'])
                    ->joinLeft('picture_vote_summary', 'pictures.id = picture_vote_summary.picture_id', null)
                    ->order(['picture_vote_summary.positive DESC', 'pictures.add_date DESC', 'pictures.id DESC'])
                    ->group('pictures.id')
                    ->limit(12);

                $db = $select->getAdapter();

                $topPictures = $this->pic()->listData($select, [
                    'width' => 4,
                    'url'   => function ($picture) use ($db, $brand) {

                        $carId = $db->fetchOne(
                            $db->select()
                                ->from('picture_item', 'item_id')
                                ->where('picture_item.picture_id = ?', $picture['id'])
                                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                                ->where('item_parent_cache.parent_id = ?', $brand['id'])
                        );

                        if (! $carId) {
                            return $this->pic()->url($picture['identity']);
                        }

                        $paths = $this->catalogue()->getCataloguePaths($carId, [
                            'toBrand'      => $brand['id'],
                            'breakOnFirst' => true
                        ]);

                        if (count($paths) <= 0) {
                            return $this->pic()->url($picture['identity']);
                        }

                        $path = $paths[0];

                        return $this->url()->fromRoute('catalogue', [
                            'action'        => 'brand-item-picture',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path'],
                            'picture_id'    => $picture['identity']
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

            foreach ($types as $key => &$type) {
                $type['links'] = $this->itemLinkTable->select([
                    'item_id' => $brand['id'],
                    'type'    => $key
                ]);
            }
            foreach ($types as $key => &$type) {
                if (count($type['links']) <= 0) {
                    unset($types[$key]);
                }
            }

            $cars = $this->catalogue()->getItemTable();

            $haveTwins = $cars->getAdapter()->fetchOne(
                $cars->getAdapter()->select()
                    ->from($cars->info('name'), 'id')
                    ->where('item.item_type_id = ?', Item::TWINS)
                    ->join(['ipc1' => 'item_parent_cache'], 'item.id = ipc1.parent_id', null)
                    ->join(['ipc2' => 'item_parent_cache'], 'ipc1.item_id = ipc2.item_id', null)
                    ->where('ipc2.parent_id = ?', $brand['id'])
                    ->limit(1)
            );

            $itemLanguageTable = new DbTable\Item\Language();
            $db = $itemLanguageTable->getAdapter();
            $orderExpr = $db->quoteInto('language = ? desc', $this->language());
            $itemLanguageRows = $itemLanguageTable->fetchAll([
                'item_id = ?' => $brand['id']
            ], new \Zend_Db_Expr($orderExpr));

            $textIds = [];
            foreach ($itemLanguageRows as $itemLanguageRow) {
                if ($itemLanguageRow->text_id) {
                    $textIds[] = $itemLanguageRow->text_id;
                }
            }

            $description = null;
            if ($textIds) {
                $description = $this->textStorage->getFirstText($textIds);
            }

            $this->sidebar()->brand([
                'brand_id' => $brand['id']
            ]);

            $inboxPictures = null;

            if ($this->user()->isAllowed('picture', 'move')) {
                $db = $this->pictureTable->getAdapter();

                $inboxPictures = $db->fetchOne(
                    $db->select()
                        ->from('pictures', 'count(distinct pictures.id)')
                        ->where('pictures.status = ?', DbTable\Picture::STATUS_INBOX)
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                        ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                        ->where('item_parent_cache.parent_id = ?', $brand['id'])
                );
            }

            $requireAttention = 0;
            $isModerator = $this->user()->inheritsRole('moder');
            if ($isModerator) {
                $requireAttention = $this->getItemModerAttentionCount($brand['id']);
            }

            return [
                'topPictures' => $topPictures,
                'link_types'  => $types,
                'haveTwins'   => $haveTwins,
                'mostsActive' => $this->mostsActive($brand['id']),
                'description' => $description,
                'factories'   => $this->getBrandFactories($brand['id']),
                'inboxPictures'    => $inboxPictures,
                'requireAttention' => $requireAttention
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
        $select = $this->selectOrderFromPictures($onlyAccepted)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->where('picture_item.item_id = ?', $brandId);

        switch ($type) {
            case 'mixed':
                $select->where('picture_item.perspective_id = ?', 25);
                break;
            case 'logo':
                $select->where('picture_item.perspective_id = ?', 22);
                break;
            default:
                $select->where(
                    'picture_item.perspective_id not in (?) or picture_item.perspective_id is null',
                    [22, 25]
                );
                break;
        }

        //print $select;

        return $select;
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
                        'picture_id' => $row['identity']
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
        return $this->typePictures('unsorted');
    }

    public function mixedAction()
    {
        return $this->typePictures('mixed');
    }

    public function logotypesAction()
    {
        return $this->typePictures('logo');
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
        return $this->typePicturesPicture('unsorted');
    }

    public function mixedPictureAction()
    {
        return $this->typePicturesPicture('mixed');
    }

    public function logotypesPictureAction()
    {
        return $this->typePicturesPicture('logo');
    }

    private function typePicturesGallery($type)
    {
        return $this->doBrandAction(function ($brand) use ($type) {

            $select = $this->typePicturesSelect($brand['id'], $type, false);

            switch ($this->params('gallery')) {
                case 'inbox':
                    $select->where('pictures.status = ?', DbTable\Picture::STATUS_INBOX);
                    break;
                case 'removing':
                    $select->where('pictures.status = ?', DbTable\Picture::STATUS_REMOVING);
                    break;
                default:
                    $select->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED);
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
        return $this->typePicturesGallery('unsorted');
    }

    public function mixedGalleryAction()
    {
        return $this->typePicturesGallery('mixed');
    }

    public function logotypesGalleryAction()
    {
        return $this->typePicturesGallery('logo');
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
            $itemTable = $this->catalogue()->getItemTable();
            $db = $itemTable->getAdapter();

            $language = $this->language();

            $rows = $db->fetchAll(
                $db->select()
                    ->from('item', [
                        'item.id',
                        'name' => 'if(item_language.name, item_language.name, item.name)',
                        'item.begin_model_year', 'item.end_model_year',
                        'spec' => 'spec.short_name',
                        'spec_full' => 'spec.name',
                        'item.body', 'item.today',
                        'item.begin_year', 'item.end_year'
                    ])
                    ->joinLeft(
                        'item_language',
                        'item.id = item_language.item_id and item_language.language = :lang',
                        null
                    )
                    ->joinLeft('spec', 'item.spec_id = spec.id', null)
                    ->where('item.id in (?)', $ids),
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

            $itemTable = $this->catalogue()->getItemTable();

            $language = $this->language();

            $path = $this->params('path');
            $path = $path ? (array)$path : [];
            $path = array_values($path);

            $db = $itemTable->getAdapter();
            $select = $db->select()
                ->from('item', [])
                ->joinLeft('item_language', 'item.id = item_language.item_id and item_language.language = :lang', null)
                ->joinLeft('spec', 'item.spec_id = spec.id', null);

            $columns = [
                'item.id',
                'item.is_concept',
                'item.item_type_id',
                'name' => 'if(length(item_language.name) > 0, item_language.name, item.name)',
                'item.begin_model_year', 'item.end_model_year',
                'spec' => 'spec.short_name',
                'item.body', 'item.today', 'item.produced', 'item.produced_exactly',
                'item.begin_year', 'item.end_year', 'item.begin_month', 'item.end_month',
                'item.is_group',
                'brand_item_catname' => 'item_parent.catname'
            ];

            $field = 'item.id';
            foreach (array_reverse($path) as $idx => $pathNode) {
                $cpAlias = 'cp'. $idx;
                $iplAlias = 'ipl'. $idx;
                $select
                    ->join(
                        [$cpAlias => 'item_parent'],
                        $field . ' = ' . $cpAlias . '.item_id',
                        null
                    )
                    ->where($cpAlias.'.catname = ?', $pathNode);
                $field = $cpAlias . '.parent_id';

                $langSortExpr = new Zend_Db_Expr(
                    $db->quoteInto($iplAlias.'.language = ? desc', $language)
                );

                $columns['cp_'.$idx.'_name'] = new Zend_Db_Expr(
                    '(' .
                        $db->select()
                            ->from([$iplAlias => 'item_parent_language'], 'name')
                            ->where($iplAlias.'.item_id = ' . $cpAlias .'.item_id')
                            ->where($iplAlias.'.parent_id = ' . $cpAlias .'.parent_id')
                            ->where('length('.$iplAlias.'.name) > 0')
                            ->order($langSortExpr)
                            ->limit(1)
                            ->assemble() .
                    ')'
                );
                //$columns['cp_'.$idx.'_name'] = $cpAlias.'.name';
                $columns['cp_'.$idx.'_item_id'] = $cpAlias.'.item_id';
            }
            $columns['top_item_id'] = $field;

            $select
                ->columns($columns)
                ->join('item_parent', $field . ' = item_parent.item_id', null)
                ->where('item_parent.parent_id = :brand_id')
                ->where('item_parent.catname = :brand_item_catname');

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
                $ids[] = $currentCar['top_item_id'];
            }
            foreach ($path as $idx => $pathNode) {
                $ridx = count($path) - $idx - 1;
                $idKey = 'cp_'.$ridx.'_item_id';
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
                if (isset($carNames[$currentCar['top_item_id']])) {
                    $topCarName = $carNames[$currentCar['top_item_id']];
                }
            } else {
                $topCarName = $carFullName;
            }

            $bvName = false;
            $bvName = $this->itemParent->getName($brand['id'], $currentCar['top_item_id'], $language);
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
                $idKey = 'cp_'.$ridx.'_item_id';

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
                    ->from('item', [
                        'brand_name'    => 'name',
                        'brand_catname' => 'catname'
                    ])
                    ->where('item.item_type_id = ?', Item::BRAND)
                    ->join('item_parent', 'item.id = item_parent.parent_id', [
                        'brand_item_catname' => 'catname'
                    ])
                    ->where('item_parent.type = ?', ItemParent::TYPE_DESIGN)
                    ->join('item_parent_cache', 'item_parent.item_id = item_parent_cache.parent_id', 'item_id')
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
                'item_id'     => $currentCar['top_item_id'],
                'is_concepts' => $currentCar['is_concept']
            ]);

            $result = $callback($currentCar, $breadcrumbs, $brand, $currentCar['brand_item_catname'], $path);

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
        $itemTable = $this->catalogue()->getItemTable();
        $db = $itemTable->getAdapter();
        $select = $db->select()
            ->from('item_parent', ['type', 'count(1)'])
            ->where('parent_id = ?', $carId)
            ->group('type');

        $pairs = $db->fetchPairs($select);

        return [
            'stock'  => isset($pairs[ItemParent::TYPE_DEFAULT])
                ? $pairs[ItemParent::TYPE_DEFAULT] : 0,
            'tuning' => isset($pairs[ItemParent::TYPE_TUNING])
                ? $pairs[ItemParent::TYPE_TUNING] : 0,
            'sport'  => isset($pairs[ItemParent::TYPE_SPORT])
                ? $pairs[ItemParent::TYPE_SPORT] : 0
        ];
    }

    public function brandItemAction()
    {
        return $this->doBrandItemAction(function (array $currentCar, $breadcrumbs, $brand, $brandItemCatname, $path) {

            $modification = null;
            $modId = (int)$this->params('mod');
            if ($modId) {
                $mTable = new DbTable\Modification();

                $modification = $mTable->find($modId)->current();
                if (! $modification) {
                    return $this->notFoundAction();
                }
            }

            $modgroupId = (int)$this->params('modgroup');
            if ($modgroupId) {
                $mgTable = new DbTable\Modification\Group();

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
                    $modId,
                    $breadcrumbs
                );
            }

            $type = $this->params('type');
            switch ($type) {
                case 'tuning':
                    $type = ItemParent::TYPE_TUNING;
                    break;
                case 'sport':
                    $type = ItemParent::TYPE_SPORT;
                    break;
                default:
                    $type = ItemParent::TYPE_DEFAULT;
                    break;
            }

            $itemTable = $this->catalogue()->getItemTable();

            $currentCarId = $currentCar['id'];

            $listCars = $itemTable->find($currentCarId);

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
                $requireAttention = $this->getItemModerAttentionCount($currentCarId);
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
                        'pictureTable'         => $this->pictureTable,
                        'perspective'          => $this->perspective,
                        'type'                 => $type == ItemParent::TYPE_DEFAULT ? $type : null,
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
                        'brandItemCatname' => $brandItemCatname,
                        'itemId'           => $currentCarId,
                        'path'             => $path,
                        'itemParent'       => $this->itemParent
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

    private function brandItemGroupModifications(int $carId, int $groupId, int $modificationId)
    {
        $mTable = new DbTable\Modification();
        $db = $mTable->getAdapter();

        $select = $mTable->select(true)
            ->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', null)
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

    private function brandItemModifications(int $carId, int $modificationId)
    {
        // modifications
        $mgTable = new DbTable\Modification\Group();

        $modificationGroups = [];

        $mgRows = $mgTable->fetchAll(
            $mgTable->select(true)
                ->join('modification', 'modification_group.id = modification.group_id', null)
                ->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', null)
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

        $modifications = $this->brandItemGroupModifications($carId, 0, $modificationId);
        if ($modifications) {
            $modificationGroups[] = [
                'name'          => null,
                'modifications' => $modifications
            ];
        }

        return $modificationGroups;
    }

    private function getModgroupPicturesSelect(int $carId, int $modId)
    {
        $db = $this->pictureTable->getAdapter();

        return $db->select()
            ->from(
                $this->pictureTable->info('name'),
                [
                    'id', 'name', 'image_id', 'crop_left', 'crop_top',
                    'crop_width', 'crop_height', 'width', 'height', 'identity'
                ]
            )
            ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
            ->where('item_parent_cache.parent_id = ?', $carId)
            ->join('modification_picture', 'pictures.id = modification_picture.picture_id', null)
            ->where('modification_picture.modification_id = ?', $modId)
            ->limit(1);
    }

    private function getModgroupPictureList(int $carId, int $modId, array $perspectiveGroupIds)
    {
        $pictures = [];
        $usedIds = [];

        $db = $this->pictureTable->getAdapter();

        foreach ($perspectiveGroupIds as $groupId) {
            $select = $this->getModgroupPicturesSelect($carId, $modId)
                ->join(
                    ['mp' => 'perspectives_groups_perspectives'],
                    'picture_item.perspective_id = mp.perspective_id',
                    null
                )
                ->where('mp.group_id = ?', $groupId)
                ->order([
                    //'item.is_concept asc',
                    'item_parent_cache.sport asc',
                    'item_parent_cache.tuning asc',
                    'mp.position'
                ])
                ->limit(1);

            /*
            if (isset($options['type'])) {
                switch ($options['type']) {
                    case ItemParent::TYPE_DEFAULT:
                        break;
                    case ItemParent::TYPE_TUNING:
                        $select->where('item_parent_cache.tuning');
                        break;
                    case ItemParent::TYPE_SPORT:
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
                        //'item.is_concept asc',
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

    private function brandItemModgroup(
        $brand,
        array $currentCar,
        $brandItemCatname,
        $path,
        int $modgroupId,
        int $modId,
        $breadcrumbs
    ) {
        $currentCarId = $currentCar['id'];

        $mTable = new DbTable\Modification();
        $imageStorage = $this->imageStorage();
        $catalogue = $this->catalogue();

        $g = $this->perspective->getPageGroupIds(2);

        $select = $mTable->select(true)
            ->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', null)
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
            $requireAttention = $this->getItemModerAttentionCount($currentCarId);
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

    private function getItemTexts(int $itemId)
    {
        $itemLanguageTable = new DbTable\Item\Language();

        $db = $itemLanguageTable->getAdapter();
        $orderExpr = $db->quoteInto('language = ? desc', $this->language());
        $itemLanguageRows = $itemLanguageTable->fetchAll([
            'item_id = ?' => $itemId
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
        int $modId,
        $breadcrumbs
    ) {
        $currentCarId = $currentCar['id'];

        $type = $this->params('type');
        switch ($type) {
            case 'tuning':
                $type = ItemParent::TYPE_TUNING;
                break;
            case 'sport':
                $type = ItemParent::TYPE_SPORT;
                break;
            default:
                $type = ItemParent::TYPE_DEFAULT;
                break;
        }

        $itemTable = $this->catalogue()->getItemTable();

        $listCars = [];

        $select = $itemTable->select(true)
            ->join('item_parent', 'item.id = item_parent.item_id', null)
            ->where('item_parent.parent_id = ?', $currentCarId)
            ->where('item_parent.type = ?', $type)
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
        if ($isLastPage && $type == ItemParent::TYPE_DEFAULT) {
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
                        'picture_id'    => $pictureRow['identity']
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
            $requireAttention = $this->getItemModerAttentionCount($currentCarId);
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

        $carLangTable = new DbTable\Item\Language();
        $carLangRows = $carLangTable->fetchAll([
            'item_id = ?' => $currentCar['id'],
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
                    'pictureTable'         => $this->pictureTable,
                    'perspective'          => $this->perspective,
                    'type'                 => $type == ItemParent::TYPE_DEFAULT ? $type : null,
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
                    'itemParent'       => $this->itemParent,
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

    private function getItemModerAttentionCount($carId)
    {
        return $this->comments->getTotalMessagesCount([
            'attention' => Comments\Attention::REQUIRED,
            'type'      => \Application\Comments::PICTURES_TYPE_ID,
            'callback'  => function (\Zend\Db\Sql\Select $select) use ($carId) {
                $select
                    ->join('pictures', 'comment_message.item_id = pictures.id', [])
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', [])
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', [])
                    ->where(['item_parent_cache.parent_id = ?' => $carId]);
            }
        ]);
    }

    private function getCarInboxCount(int $carId)
    {
        $select = $this->pictureTable->select(true)
            ->where('pictures.status = ?', DbTable\Picture::STATUS_INBOX)
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
    private function getBrandItemPicturesSelect(int $carId, bool $exact, bool $onlyAccepted = true)
    {
        $select = $this->selectFromPictures($onlyAccepted)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->joinLeft('perspectives', 'picture_item.perspective_id = perspectives.id', null)
            ->order(array_merge(
                ['perspectives.position'],
                $this->catalogue()->picturesOrdering()
            ));

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
        return $this->doBrandItemAction(function (array $currentCar, $breadcrumbs, $brand, $brandItemCatname, $path) {

            $exact = (bool)$this->params('exact');

            $select = $this->getBrandItemPicturesSelect($currentCar['id'], $exact);

            $modification = null;
            $modId = (int)$this->params('mod');
            if ($modId) {
                $mTable = new DbTable\Modification();

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
                        'picture_id'    => $row['identity']
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

        if ($picture->status == DbTable\Picture::STATUS_REMOVING) {
            $user = $this->user()->get();
            if (! $user) {
                return $this->notFoundAction();
            }

            if ($isModer || ($user->id == $picture->owner_id)) {
                //$this->getResponse()->setStatusCode(404);
            } else {
                return $this->notFoundAction();
            }

            $select->where('pictures.status = ?', DbTable\Picture::STATUS_REMOVING);
        } elseif ($picture->status == DbTable\Picture::STATUS_INBOX) {
            $select->where('pictures.status = ?', DbTable\Picture::STATUS_INBOX);
        } else {
            $select->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED);
        }

        return $callback($select, $picture);
    }

    private function galleryType($picture)
    {
        if ($picture->status == DbTable\Picture::STATUS_REMOVING) {
            $gallery = 'removing';
        } elseif ($picture->status == DbTable\Picture::STATUS_INBOX) {
            $gallery = 'inbox';
        } else {
            $gallery = null;
        }

        return $gallery;
    }

    public function brandItemPictureAction()
    {
        return $this->doBrandItemAction(function (array $currentCar, $breadcrumbs) {
            $exact = (bool)$this->params('exact');

            $select = $this->getBrandItemPicturesSelect($currentCar['id'], $exact, false);

            return $this->pictureAction($select, function ($select, $picture) use ($breadcrumbs) {
                return [
                    'breadcrumbs' => $breadcrumbs,
                    'picture'     => array_replace(
                        $this->pic()->picPageData($picture, $select),
                        [
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
        return $this->doBrandItemAction(function (array $currentCar) {

            $exact = (bool)$this->params('exact');
            $select = $this->getBrandItemPicturesSelect($currentCar['id'], $exact, false);

            switch ($this->params('gallery')) {
                case 'inbox':
                    $select->where('pictures.status = ?', DbTable\Picture::STATUS_INBOX);
                    break;
                case 'removing':
                    $select->where('pictures.status = ?', DbTable\Picture::STATUS_REMOVING);
                    break;
                default:
                    $select->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED);
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
        return $this->doBrandItemAction(function (array $currentCar, $breadcrumbs) {

            $currentCarId = $currentCar['id'];

            $type = $this->params('type');
            switch ($type) {
                case 'tuning':
                    $type = ItemParent::TYPE_TUNING;
                    break;
                case 'sport':
                    $type = ItemParent::TYPE_SPORT;
                    break;
                default:
                    $type = ItemParent::TYPE_DEFAULT;
                    break;
            }

            //$list = $this->catalogue()->getItemTable()->find($brandItemRow->item_id);

            $itemTable = $this->catalogue()->getItemTable();

            $select = $itemTable->select(true)
                ->order($this->carsOrder());
            if ($currentCar['is_group']) {
                $select
                    ->where('item_parent.type = ?', $type)
                    ->join('item_parent', 'item.id = item_parent.item_id', null)
                    ->where('item_parent.parent_id = ?', $currentCarId);
            } else {
                $select
                    ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $currentCarId)
                    ->where('item_parent_cache.diff <= 1');
            }
            $childCars = $itemTable->fetchAll($select);

            if (count($childCars) <= 0) {
                $select = $itemTable->select(true)
                    ->order($this->carsOrder());
                if ($currentCar['is_group']) {
                    $select
                        ->where('item_parent.type = ?', $type)
                        ->join('item_parent', 'item.id = item_parent.item_id', null)
                        ->where('item_parent.parent_id = ?', $currentCarId);
                } else {
                    $select
                        ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', null)
                        ->where('item_parent_cache.parent_id = ?', $currentCarId);
                }

                $childCars = $itemTable->fetchAll($select);
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

    private function mostsActive(int $brandId)
    {
        $itemTable = new DbTable\Item();
        $db = $itemTable->getAdapter();
        $carsCount = $db->fetchOne(
            $db->select()
                ->from($itemTable->info('name'), 'count(1)')
                ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $brandId)
        );

        return $carsCount >= $this->mostsMinCarsCount;
    }

    public function brandMostsAction()
    {
        return $this->doBrandAction(function ($brand) {

            if (! $this->mostsActive($brand['id'])) {
                return $this->notFoundAction();
            }

            $language = $this->language();
            $yearsCatname = $this->params('years_catname');
            $carTypeCatname = $this->params('shape_catname');
            $mostCatname = $this->params('most_catname');

            $data = $this->mosts->getData([
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

            $names = $this->pictureTable->getNameData($allPictures, [
                'language' => $language
            ]);

            $idx = 0;
            foreach ($data['carList']['cars'] as &$car) {
                $pictures = [];

                $paths = $this->catalogue()->getCataloguePaths($car['car']['id'], [
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
                                'picture_id'    => $picture['identity']
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

                $car['name'] = $this->itemModel->getNameData($car['car'], $language);
                $car['pictures'] = $pictures;
            }
            unset($car);

            $sideBarModel = new ViewModel($data);
            $sideBarModel->setTemplate('application/mosts/sidebar');
            $this->layout()->addChild($sideBarModel, 'sidebar');

            return $data;
        });
    }

    public function enginesAction()
    {
        return $this->doBrandAction(function ($brand) {
            $select = $this->catalogue()->getItemTable()->select(true)
                ->where('item.item_type_id = ?', Item::ENGINE)
                ->join('item_parent', 'item.id = item_parent.item_id', null)
                ->where('item_parent.parent_id = ?', $brand['id'])
                ->order($this->carsOrder());

            $paginator = $this->carsPaginator($select, $this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            $this->sidebar()->brand([
                'brand_id' => $brand['id']
            ]);

            return [
                'paginator' => $paginator,
                'listData'  => $this->car()->listData($paginator->getCurrentItems(), [
                    'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                        'pictureTable'         => $this->pictureTable,
                        'perspective'          => $this->perspective,
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
                        'specsService'    => $this->specsService
                    ])
                ])
            ];
        });
    }
}
