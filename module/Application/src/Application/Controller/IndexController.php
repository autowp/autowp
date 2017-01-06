<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\User\Model\DbTable\User;

use Application\Model\CarOfDay;
use Application\Model\Brand as BrandModel;
use Application\Model\DbTable;
use Application\Model\DbTable\Perspective\Group as PerspectiveGroup;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Vehicle\ParentTable as VehicleParent;
use Application\Model\DbTable\Vehicle\Row as VehicleRow;
use Application\Model\Twins;
use Application\Service\SpecificationsService;
use Application\ItemNameFormatter;

use Zend_Db_Expr;

class IndexController extends AbstractActionController
{
    private $cache;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var VehicleParent
     */
    private $vehicleParentTable;

    /**
     * @var CarOfDay
     */
    private $carOfDay;

    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    public function __construct(
        $cache,
        SpecificationsService $specsService,
        CarOfDay $carOfDay,
        ItemNameFormatter $itemNameFormatter
    ) {
        $this->cache = $cache;
        $this->specsService = $specsService;
        $this->carOfDay = $carOfDay;
        $this->itemNameFormatter = $itemNameFormatter;
    }

    /**
     * @return VehicleParent
     */
    private function getVehicleParentTable()
    {
        return $this->vehicleParentTable
            ? $this->vehicleParentTable
            : $this->vehicleParentTable = new VehicleParent();
    }

    private function getOrientedPictureList($car)
    {
        $perspectivesGroups = new PerspectiveGroup();

        $db = $perspectivesGroups->getAdapter();
        $perspectivesGroupIds = $db->fetchCol(
            $db->select()
                ->from($perspectivesGroups->info('name'), 'id')
                ->where('page_id = ?', 6)
                ->order('position')
        );

        $pTable = $this->catalogue()->getPictureTable();
        $pictures = [];

        $db = $pTable->getAdapter();
        $usedIds = [];

        foreach ($perspectivesGroupIds as $groupId) {
            $picture = null;

            $select = $pTable->select(true)
                ->where('mp.group_id = ?', $groupId)
                ->where('pictures.status IN (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $car->id)
                ->joinRight(
                    ['mp' => 'perspectives_groups_perspectives'],
                    'picture_item.perspective_id = mp.perspective_id',
                    null
                )
                ->order([
                    'item_parent_cache.sport', 'item_parent_cache.tuning', 'mp.position',
                    'pictures.width DESC', 'pictures.height DESC'
                ])
                ->limit(1);
            if ($usedIds) {
                $select->where('pictures.id not in (?)', $usedIds);
            }
            $picture = $pTable->fetchRow($select);

            if ($picture) {
                $pictures[] = $picture;
                $usedIds[] = $picture->id;
            } else {
                $pictures[] = null;
            }
        }

        $resorted = [];
        foreach ($pictures as $picture) {
            if ($picture) {
                $resorted[] = $picture;
            }
        }
        foreach ($pictures as $picture) {
            if (! $picture) {
                $resorted[] = null;
            }
        }
        $pictures = $resorted;

        $left = [];
        foreach ($pictures as $key => $picture) {
            if (! $picture) {
                $left[] = $key;
            }
        }

        if (count($left) > 0) {
            $select = $pTable->select(true)
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $car->id)
                ->where('pictures.status IN (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
                //->order('ratio DESC')
                ->limit(count($left));

            if (count($usedIds) > 0) {
                $select->where('pictures.id NOT IN (?)', $usedIds);
            }

            foreach ($pTable->fetchAll($select) as $pic) {
                $key = array_shift($left);
                $pictures[$key] = $pic;
            }
        }

        return $pictures;
    }

    private function carLinks(VehicleRow $car)
    {
        $items = [];

        $itemTable = $this->catalogue()->getItemTable();

        $db = $itemTable->getAdapter();
        $totalPictures = $db->fetchOne(
            $db->select()
                ->from('pictures', new Zend_Db_Expr('COUNT(1)'))
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $car->id)
                ->where('pictures.status IN (?)', [Picture::STATUS_NEW, Picture::STATUS_ACCEPTED])
        );

        $language = $this->language();

        if ($car->item_type_id == DbTable\Item\Type::CATEGORY) {
            $items[] = [
                'icon'  => 'align-left',
                'url'   => $this->url()->fromRoute('categories', [
                    'action'           => 'category',
                    'category_catname' => $car->catname,
                ]),
                'text'  => $this->translate('carlist/details')
            ];

            if ($totalPictures > 6) {
                $items[] = [
                    'icon'  => 'th',
                    'url'   => $this->url()->fromRoute('categories', [
                        'action'           => 'category-pictures',
                        'category_catname' => $car->catname,
                    ]),
                    'text'  => $this->translate('carlist/all pictures'),
                    'count' => $totalPictures
                ];
            }
        } else {
            $cataloguePaths = $this->catalogue()->cataloguePaths($car);

            if ($totalPictures > 6) {
                foreach ($cataloguePaths as $path) {
                    $url = $this->url()->fromRoute('catalogue', [
                        'action'        => 'brand-item-pictures',
                        'brand_catname' => $path['brand_catname'],
                        'car_catname'   => $path['car_catname'],
                        'path'          => $path['path']
                    ]);
                    $items[] = [
                        'icon'  => 'th',
                        'url'   => $url,
                        'text'  => $this->translate('carlist/all pictures'),
                        'count' => $totalPictures
                    ];
                    break;
                }
            }

            if ($this->specsService->hasSpecs($car->id)) {
                foreach ($cataloguePaths as $path) {
                    $items[] = [
                        'icon'  => 'list-alt',
                        'url'   => $this->url()->fromRoute('catalogue', [
                            'action'        => 'brand-item-specifications',
                            'brand_catname' => $path['brand_catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path']
                        ]),
                        'text'  => $this->translate('carlist/specifications')
                    ];
                    break;
                }
            }

            $twins = new Twins();
            foreach ($twins->getCarGroups($car->id) as $twinsGroup) {
                $items[] = [
                    'icon'  => 'adjust',
                    'url'   => $this->url()->fromRoute('twins/group', [
                        'id' => $twinsGroup['id']
                    ]),
                    'text'  => $this->translate('carlist/twins')
                ];
            }

            $categoryRows = $db->fetchAll(
                $db->select()
                    ->from($itemTable->info('name'), [
                        'catname', 'begin_year', 'end_year',
                        'name' => new Zend_Db_Expr('IF(LENGTH(item_language.name)>0,item_language.name,item.name)')
                    ])
                    ->where('item.item_type_id = ?', DbTable\Item\Type::CATEGORY)
                    ->joinLeft(
                        'item_language',
                        'item.id = item_language.item_id and item_language.language = :language',
                        null
                    )
                    ->join('item_parent', 'item.id = item_parent.parent_id', null)
                    ->join(['top_item' => 'item'], 'item_parent.item_id = top_item.id', null)
                    ->where('top_item.item_type_id IN (?)', [DbTable\Item\Type::VEHICLE, DbTable\Item\Type::ENGINE])
                    ->join('item_parent_cache', 'top_item.id = item_parent_cache.parent_id', 'item_id')
                    ->where('item_parent_cache.item_id = :item_id')
                    ->group(['item_parent_cache.item_id', 'item.id'])
                    ->bind([
                        'language' => $language,
                        'item_id'  => $car['id']
                    ])
            );

            foreach ($categoryRows as $category) {
                $items[] = [
                    'icon'  => 'tag',
                    'url'   => $this->url()->fromRoute('categories', [
                        'action'           => 'category',
                        'category_catname' => $category['catname'],
                    ]),
                    'text'  => $this->itemNameFormatter->format(
                        $category,
                        $language
                    )
                ];
            }
        }

        return $items;
    }

    private function brands()
    {
        $language = $this->language();

        $cacheKey = 'INDEX_BRANDS_HTML261' . $language;
        $brands = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            // cache missing
            $brandModel = new BrandModel();

            $items = $brandModel->getTopBrandsList($language);
            foreach ($items as &$item) {
                $item['url'] = $this->url()->fromRoute('catalogue', [
                    'action'        => 'brand',
                    'brand_catname' => $item['catname'],
                ]);
                $item['new_cars_url'] = $this->url()->fromRoute('brands/newcars', [
                    'brand_id' => $item['id']
                ]);
            }
            unset($item);

            $brands = [
                'brands'      => $items,
                'totalBrands' => $brandModel->getTotalCount()
            ];

            $this->cache->setItem($cacheKey, $brands);
        }

        return $brands;
    }

    public function getCategoryPaths($carId, array $options = [])
    {
        $carId = (int)$carId;
        if (! $carId) {
            throw new Exception("carId not provided");
        }

        $breakOnFirst = isset($options['breakOnFirst']) && $options['breakOnFirst'];

        $result = [];

        $db = $this->getVehicleParentTable()->getAdapter();

        $select = $db->select()
            ->from('item_parent', 'item_id')
            ->join('item', 'item_parent.parent_id = item.id', 'catname')
            ->where('item.item_type_id = ?', DbTable\Item\Type::CATEGORY)
            ->where('item_parent.item_id = ?', $carId);

        if ($breakOnFirst) {
            $select->limit(1);
        }

        $categoryVehicleRows = $db->fetchAll($select);
        foreach ($categoryVehicleRows as $categoryVehicleRow) {
            $result[] = [
                'category_catname' => $categoryVehicleRow['catname'],
                'item_id'          => $categoryVehicleRow['item_id'],
                'path'             => []
            ];
        }

        if ($breakOnFirst && count($result)) {
            return $result;
        }

        $parents = $this->getVehicleParentTable()->fetchAll([
            'item_id = ?' => $carId
        ]);

        foreach ($parents as $parent) {
            $paths = $this->getCategoryPaths($parent->parent_id, $options);

            foreach ($paths as $path) {
                $result[] = [
                    'category_catname' => $path['category_catname'],
                    'item_id'          => $path['item_id'],
                    'path'             => array_merge($path['path'], [$parent->catname])
                ];
            }

            if ($breakOnFirst && count($result)) {
                return $result;
            }
        }

        return $result;
    }

    private function carOfDay()
    {
        $language = $this->language();
        $httpsFlag = $this->getRequest()->getUri()->getScheme();

        $carId = $this->carOfDay->getCurrent();

        $carOfDayName = null;
        $carOfDayPicturesData = null;
        $carOfDayLinks = [];

        if ($carId) {
            $itemTable = $this->catalogue()->getItemTable();
            $carOfDay = $itemTable->find($carId)->current();
            if ($carOfDay) {
                $key = 'CAR_OF_DAY_90_' . $carOfDay->id . '_' . $language . '_' . $httpsFlag;

                $carOfDayInfo = $this->cache->getItem($key, $success);
                if (! $success) {
                    $carOfDayPictures = $this->getOrientedPictureList($carOfDay);

                    // images
                    $formatRequests = [];
                    foreach ($carOfDayPictures as $idx => $picture) {
                        if ($picture) {
                            $format = $idx > 0 ? 'picture-thumb' : 'picture-thumb-medium';
                            $formatRequests[$format][$idx] = $picture->getFormatRequest();
                        }
                    }

                    $imageStorage = $this->imageStorage();

                    $imagesInfo = [];
                    foreach ($formatRequests as $format => $requests) {
                        $imagesInfo[$format] = $imageStorage->getFormatedImages($requests, $format);
                    }

                    // names
                    $notEmptyPics = [];
                    foreach ($carOfDayPictures as $idx => $picture) {
                        if ($picture) {
                            $notEmptyPics[] = $picture;
                        }
                    }
                    $pictureTable = $this->catalogue()->getPictureTable();
                    $names = $pictureTable->getNameData($notEmptyPics, [
                        'language' => $language
                    ]);

                    $carParentTable = new VehicleParent();

                    $paths = $carParentTable->getPaths($carOfDay->id, [
                        'breakOnFirst' => true
                    ]);

                    $categoryPath = false;
                    if (! $paths) {
                        $categoryPaths = $this->getCategoryPaths($carOfDay->id, [
                            'breakOnFirst' => true
                        ]);
                    }

                    $carOfDayPicturesData = [];
                    foreach ($carOfDayPictures as $idx => $row) {
                        if ($row) {
                            $format = $idx > 0 ? 'picture-thumb' : 'picture-thumb-medium';

                            $url = null;
                            foreach ($paths as $path) {
                                $url = $this->url()->fromRoute('catalogue', [
                                    'action'        => 'brand-item-picture',
                                    'brand_catname' => $path['brand_catname'],
                                    'car_catname'   => $path['car_catname'],
                                    'path'          => $path['path'],
                                    'picture_id'    => $row['identity']
                                ]);
                            }

                            if (! $url) {
                                foreach ($categoryPaths as $path) {
                                    $url = $this->url()->fromRoute('categories', [
                                        'action'           => 'category-picture',
                                        'category_catname' => $path['category_catname'],
                                        'item_id'          => $path['item_id'],
                                        'path'             => $path['path'],
                                        'picture_id'       => $row['identity']
                                    ]);
                                }
                            }

                            $carOfDayPicturesData[] = [
                                'src'  => isset($imagesInfo[$format][$idx])
                                    ? $imagesInfo[$format][$idx]->getSrc()
                                    : null,
                                'name' => isset($names[$row['id']]) ? $names[$row['id']] : null,
                                'url'  => $url
                            ];
                        }
                    }

                    $carOfDayName = $carOfDay->getNameData($language);

                    $carOfDayInfo = [
                        'name'     => $carOfDayName,
                        'pictures' => $carOfDayPicturesData,
                    ];

                    $this->cache->setItem($key, $carOfDayInfo);
                } else {
                    $carOfDayName = $carOfDayInfo['name'];
                    $carOfDayPicturesData = $carOfDayInfo['pictures'];
                }
            }

            $carOfDayLinks = $this->carLinks($carOfDay);
        }

        return [
            'name'     => $carOfDayName,
            'pictures' => $carOfDayPicturesData,
            'links'    => $carOfDayLinks
        ];
    }

    private function factories()
    {
        $cacheKey = 'INDEX_FACTORIES_1';
        $factories = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $table = new DbTable\Factory();

            $db = $table->getAdapter();

            $factories = $db->fetchAll(
                $db->select()
                    ->from('factory', ['id', 'name', 'count' => new Zend_Db_Expr('count(1)')])
                    ->join('factory_item', 'factory.id = factory_item.factory_id', null)
                    ->join('item_parent_cache', 'factory_item.item_id = item_parent_cache.parent_id', null)
                    ->where('not item_parent_cache.tuning')
                    ->where('not item_parent_cache.sport')
                    ->group('factory.id')
                    ->order('count desc')
                    ->limit(8)
            );

            foreach ($factories as &$factory) {
                $factory['url'] = $this->url()->fromRoute('factories/factory', [
                    'id' => $factory['id']
                ]);
            }
            unset($factory);

            $this->cache->setItem($cacheKey, $factories);
        }

        return $factories;
    }

    public function indexAction()
    {
        $brands = $this->catalogue()->getBrandTable();
        $pictures = $this->catalogue()->getPictureTable();
        $itemTable = $this->catalogue()->getItemTable();

        $language = $this->language();

        $select = $pictures->select(true)
            ->where('pictures.accept_datetime > DATE_SUB(CURDATE(), INTERVAL 3 DAY)')
            ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
            ->order(['pictures.accept_datetime DESC', 'pictures.id DESC'])
            ->limit(6);

        $newPicturesData = $this->pic()->listData($select, [
            'width' => 3
        ]);

        // categories
        $cacheKey = 'INDEX_CATEGORY10_' . $language;
        $destinations = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $categoryAdapter = $itemTable->getAdapter();
            $itemLangTable = new DbTable\Vehicle\Language();

            $expr = 'COUNT(IF(item_parent.timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY), 1, NULL))';

            $items = $categoryAdapter->fetchAll(
                $categoryAdapter->select()
                    ->from(
                        ['category' => $itemTable->info('name')],
                        [
                            'id',
                            'cars_count'     => 'COUNT(1)',
                            'new_cars_count' => new Zend_Db_Expr($expr)
                        ]
                    )
                    ->where('category.item_type_id = ?', DbTable\Item\Type::CATEGORY)
                    ->joinLeft(['top_category_parent' => 'item_parent'], 'category.id = top_category_parent.item_id', null)
                    ->where('top_category_parent.parent_id is null')
                    ->join('item_parent', 'category.id = item_parent.parent_id', null)
                    ->join(['top_item' => 'item'], 'item_parent.item_id = top_item.id', null)
                    ->where('top_item.item_type_id IN (?)', [DbTable\Item\Type::VEHICLE, DbTable\Item\Type::ENGINE])
                    ->join('item_parent_cache', 'top_item.id = item_parent_cache.parent_id', 'item_id')
                    ->join('item', 'item_parent_cache.item_id = item.id', null)
                    ->where('not item.is_group')
                    ->group('category.id')
                    ->order('new_cars_count DESC')
                    ->limit(15)
            );

            $destinations = [];
            foreach ($items as $item) {
                $row = $itemTable->find($item['id'])->current();
                if (! $row) {
                    continue;
                }

                $langRow = $itemLangTable->fetchRow([
                    'language = ?' => $language,
                    'item_id = ?'  => $row->id
                ]);

                $destinations[] = [
                    'url'            => $this->url()->fromRoute('categories', [
                        'action'           => 'category',
                        'category_catname' => $row->catname,
                    ]),
                    'short_name'     => $langRow ? $langRow->name : $row->name,
                    'cars_count'     => $item['cars_count'],
                    'new_cars_count' => $item['new_cars_count']
                ];
            }

            $this->cache->setItem($cacheKey, $destinations);
        }

        // БЛИЗНЕЦЫ
        $cacheKey = 'INDEX_INTERESTS_TWINS_BLOCK_26_' . $language;
        $twinsBlock = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $twins = new Twins();

            $twinsBrands = $twins->getBrands([
                'language' => $language,
                'limit'    => 20
            ]);

            foreach ($twinsBrands as &$brand) {
                $brand['url'] = $this->url()->fromRoute('twins/brand', [
                    'brand_catname' => $brand['folder']
                ]);
            }
            unset($brand);

            $twinsBlock = [
                'brands'     => $twinsBrands,
                'more_count' => $twins->getTotalBrandsCount()
            ];

            $this->cache->setItem($cacheKey, $twinsBlock);
        }

        $userTable = new User();

        $cacheKey = 'INDEX_SPEC_CARS_13_' . $language;
        $cars = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $itemTable = $this->catalogue()->getItemTable();

            $cars = $itemTable->fetchAll(
                $select = $itemTable->select(true)
                    ->join('attrs_user_values', 'item.id = attrs_user_values.item_id', null)
                    ->where('update_date > DATE_SUB(NOW(), INTERVAL 3 DAY)')
                    ->having('count(attrs_user_values.attribute_id) > 10')
                    ->group('item.id')
                    ->order('MAX(attrs_user_values.update_date) DESC')
                    ->limit(4)
            );

            $this->cache->setItem($cacheKey, $cars);
        }

        $specsCars = $this->car()->listData($cars, [
            'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                'type'                 => null,
                'onlyExactlyPictures'  => false,
                'dateSort'             => false,
                'disableLargePictures' => true,
                'perspectivePageId'    => 1,
                'onlyChilds'           => []
            ]),
            'listBuilder' => new \Application\Model\Item\ListBuilder([
                'catalogue' => $this->catalogue(),
                'router'    => $this->getEvent()->getRouter(),
                'picHelper' => $this->getPluginManager()->get('pic')
            ]),
            'disableDescription'   => true,
            'callback'             => function (&$item) use ($userTable) {
                $contribPairs = $this->specsService->getContributors([$item['id']]);
                if ($contribPairs) {
                    $item['contributors'] = $userTable->fetchAll(
                        $userTable->select(true)
                            ->where('id IN (?)', array_keys($contribPairs))
                            ->where('not deleted')
                    );
                } else {
                    $item['contributors'] = [];
                }
            }
        ]);

        return [
            'brands'      => $this->brands(),
            'factories'   => $this->factories(),
            'twinsBlock'  => $twinsBlock,
            'categories'  => $destinations,
            'newPictures' => $newPicturesData,
            'carOfDay'    => $this->carOfDay(),
            'specsCars'   => $specsCars,
            'mosts'       => [
                '/mosts/fastest/roadster'          => 'mosts/fastest/roadster',
                '/mosts/mighty/sedan/today'        => 'mosts/mighty/sedan/today',
                '/mosts/dynamic/universal/2000-09' => 'mosts/dynamic/universal/2000-09',
                '/mosts/heavy/truck'               => 'mosts/heavy/truck'
            ]
        ];
    }
}
