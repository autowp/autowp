<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\User\Model\DbTable\User;

use Application\Model\CarOfDay;
use Application\Model\Brand as BrandModel;
use Application\Model\DbTable\Category;
use Application\Model\DbTable\Category\Language as CategoryLanguage;
use Application\Model\DbTable\Category\Vehicle as CategoryVehicle;
use Application\Model\DbTable\Factory;
use Application\Model\DbTable\Perspective\Group as PerspectiveGroup;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Vehicle\ParentTable as VehicleParent;
use Application\Model\DbTable\Vehicle\Row as VehicleRow;
use Application\Model\Twins;
use Application\Service\SpecificationsService;

use Zend_Db_Expr;

class IndexController extends AbstractActionController
{
    private $cache;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var CategoryVehicle
     */
    private $categoryVehicleTable;

    /**
     * @var VehicleParent
     */
    private $vehicleParentTable;

    /**
     * @var CarOfDay
     */
    private $carOfDay;

    public function __construct(
        $cache,
        SpecificationsService $specsService,
        CarOfDay $carOfDay
    ) {
        $this->cache = $cache;
        $this->specsService = $specsService;
        $this->carOfDay = $carOfDay;
    }

    /**
     * @return CategoryVehicle
     */
    private function getCategoryVehicleTable()
    {
        return $this->categoryVehicleTable
            ? $this->categoryVehicleTable
            : $this->categoryVehicleTable = new CategoryVehicle();
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
                ->join('car_parent_cache', 'picture_item.item_id = car_parent_cache.car_id', null)
                ->where('car_parent_cache.parent_id = ?', $car->id)
                ->joinRight(
                    ['mp' => 'perspectives_groups_perspectives'],
                    'picture_item.perspective_id = mp.perspective_id',
                    null
                )
                ->order([
                    'car_parent_cache.sport', 'car_parent_cache.tuning', 'mp.position',
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
                ->join('car_parent_cache', 'picture_item.item_id = car_parent_cache.car_id', null)
                ->where('car_parent_cache.parent_id = ?', $car->id)
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

        $db = $car->getTable()->getAdapter();
        $totalPictures = $db->fetchOne(
            $db->select()
                ->from('pictures', new Zend_Db_Expr('COUNT(1)'))
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->join('car_parent_cache', 'picture_item.item_id = car_parent_cache.car_id', null)
                ->where('car_parent_cache.parent_id = ?', $car->id)
                ->where('pictures.status IN (?)', [Picture::STATUS_NEW, Picture::STATUS_ACCEPTED])
        );

        $cataloguePaths = $this->catalogue()->cataloguePaths($car);

        if ($totalPictures > 6) {
            foreach ($cataloguePaths as $path) {
                $url = $this->url()->fromRoute('catalogue', [
                    'action'        => 'brand-car-pictures',
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

        if ($this->specsService->hasSpecs(1, $car->id)) {
            foreach ($cataloguePaths as $path) {
                $items[] = [
                    'icon'  => 'list-alt',
                    'url'   => $this->url()->fromRoute('catalogue', [
                        'action'        => 'brand-car-specifications',
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
                ->from('category', ['name', 'catname'])
                ->join('category_car', 'category.id = category_car.category_id', null)
                ->join('car_parent_cache', 'category_car.car_id = car_parent_cache.parent_id', null)
                ->joinLeft(
                    'category_language',
                    'category.id = category_language.category_id and category_language.language = :language',
                    ['lang_name' => 'name']
                )
                ->where('car_parent_cache.car_id = :car_id')
                ->group(['category.id'])
                ->bind([
                    'language' => $this->language(),
                    'car_id'   => $car['id']
                ])
        );

        foreach ($categoryRows as $category) {
            $items[] = [
                'icon'  => 'tag',
                'url'   => $this->url()->fromRoute('categories', [
                    'action'           => 'category',
                    'category_catname' => $category['catname'],
                ]),
                'text'  => $category['lang_name'] ? $category['lang_name'] : $category['name'],
            ];
        }

        return $items;
    }

    private function brands()
    {
        $language = $this->language();

        $cacheKey = 'INDEX_BRANDS_HTML260' . $language;
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

        $db = $this->getCategoryVehicleTable()->getAdapter();

        $select = $db->select()
            ->from('category_car', 'car_id')
            ->join('category', 'category_car.category_id = category.id', 'catname')
            ->where('category_car.car_id = ?', $carId);

        if ($breakOnFirst) {
            $select->limit(1);
        }

        $categoryVehicleRows = $db->fetchAll($select);
        foreach ($categoryVehicleRows as $categoryVehicleRow) {
            $result[] = [
                'category_catname' => $categoryVehicleRow['catname'],
                'car_id'           => $categoryVehicleRow['car_id'],
                'path'             => []
            ];
        }

        if ($breakOnFirst && count($result)) {
            return $result;
        }

        $parents = $this->getVehicleParentTable()->fetchAll([
            'car_id = ?' => $carId
        ]);

        foreach ($parents as $parent) {
            $paths = $this->getCategoryPaths($parent->parent_id, $options);

            foreach ($paths as $path) {
                $result[] = [
                    'category_catname' => $path['category_catname'],
                    'car_id'           => $path['car_id'],
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
            $carTable = $this->catalogue()->getCarTable();
            $carOfDay = $carTable->find($carId)->current();
            if ($carOfDay) {
                $key = 'CAR_OF_DAY_84_' . $carOfDay->id . '_' . $language . '_' . $httpsFlag;

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

                            $identity = $row['identity'] ? $row['identity'] : $row['id'];

                            $url = null;
                            foreach ($paths as $path) {
                                $url = $this->url()->fromRoute('catalogue', [
                                    'action'        => 'brand-car-picture',
                                    'brand_catname' => $path['brand_catname'],
                                    'car_catname'   => $path['car_catname'],
                                    'path'          => $path['path'],
                                    'picture_id'    => $identity
                                ]);
                            }

                            if (! $url) {
                                foreach ($categoryPaths as $path) {
                                    $url = $this->url()->fromRoute('categories', [
                                        'action'           => 'category-picture',
                                        'category_catname' => $path['category_catname'],
                                        'car_id'           => $path['car_id'],
                                        'path'             => $path['path'],
                                        'picture_id'       => $identity
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
            $table = new Factory();

            $db = $table->getAdapter();

            $factories = $db->fetchAll(
                $db->select()
                    ->from('factory', ['id', 'name', 'count' => new Zend_Db_Expr('count(1)')])
                    ->join('factory_car', 'factory.id = factory_car.factory_id', null)
                    ->join('car_parent_cache', 'factory_car.car_id = car_parent_cache.parent_id', null)
                    ->where('not car_parent_cache.tuning')
                    ->where('not car_parent_cache.sport')
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
        $cacheKey = 'INDEX_CATEGORY8_' . $language;
        $destinations = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $categoryTable = new Category();
            $categoryAdapter = $categoryTable->getAdapter();
            $categoryLangTable = new CategoryLanguage();

            $expr = 'COUNT(IF(category_car.add_datetime > DATE_SUB(NOW(), INTERVAL 7 DAY), 1, NULL))';

            $items = $categoryAdapter->fetchAll(
                $categoryAdapter->select()
                    ->from(
                        $categoryTable->info('name'),
                        [
                            'id',
                            'cars_count'     => 'COUNT(1)',
                            'new_cars_count' => new Zend_Db_Expr($expr)
                        ]
                    )
                    ->join(['cp' => 'category_parent'], 'category.id = cp.parent_id', null)
                    ->join('category_car', 'cp.category_id = category_car.category_id', null)
                    ->where('category.parent_id is null')
                    ->join('car_parent_cache', 'category_car.car_id = car_parent_cache.parent_id', null)
                    ->join('cars', 'car_parent_cache.car_id = cars.id', null)
                    ->where('not cars.is_group')
                    ->group('category.id')
                    ->order('new_cars_count DESC')
                    ->limit(15)
            );

            $destinations = [];
            foreach ($items as $item) {
                $row = $categoryTable->find($item['id'])->current();
                if (! $row) {
                    continue;
                }

                $langRow = $categoryLangTable->fetchRow([
                    'language = ?'    => $language,
                    'category_id = ?' => $row->id
                ]);

                $destinations[] = [
                    'url'            => $this->url()->fromRoute('categories', [
                        'action'           => 'category',
                        'category_catname' => $row->catname,
                    ]),
                    'short_name'     => $langRow ? $langRow->short_name : $row->short_name,
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

        $cacheKey = 'INDEX_SPEC_CARS_11_' . $language;
        $cars = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $carTable = $this->catalogue()->getCarTable();

            $cars = $carTable->fetchAll(
                $select = $carTable->select(true)
                    ->join('attrs_user_values', 'cars.id = attrs_user_values.item_id', null)
                    ->where('update_date > DATE_SUB(NOW(), INTERVAL 1 DAY)')
                    ->where('attrs_user_values.item_type_id = ?', 1)
                    ->having('count(attrs_user_values.attribute_id) > 10')
                    ->group('cars.id')
                    ->order('MAX(attrs_user_values.update_date) DESC')
                    ->limit(4)
            );

            $this->cache->setItem($cacheKey, $cars);
        }

        $specsCars = $this->car()->listData($cars, [
            'disableLargePictures' => true,
            'perspectiveGroup'     => 1,
            'allowUpPictures'      => true,
            'disableDescription'   => true,
            'callback'             => function (&$item) use ($userTable) {
                $contribPairs = $this->specsService->getContributors(1, [$item['id']]);
                if ($contribPairs) {
                    $item['contributors'] = $userTable->fetchAll(
                        $userTable->select(true)
                            ->where('id IN (?)', array_keys($contribPairs))
                            ->where('not deleted')
                    );
                } else {
                    $item['contributors'] = [];
                }
            },
            'pictureUrl' => function ($listCar, $picture) {
                return $this->pic()->href($picture);
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
