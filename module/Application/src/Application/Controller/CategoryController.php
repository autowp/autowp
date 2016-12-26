<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Vehicle\ParentTable as VehicleParent;
use Application\Paginator\Adapter\Zend1DbTableSelect;

use Zend_Db_Expr;

class CategoryController extends AbstractActionController
{
    private $otherCategoryName = 'Other';

    private $cache;

    private $textStorage;

    /**
     * @var DbTable\Vehicle
     */
    private $itemTable;

    /**
     * @var DbTable\Vehicle\Item
     */
    private $itemLanguageTable;

    public function __construct($cache, $textStorage)
    {
        $this->cache = $cache;
        $this->textStorage = $textStorage;

        $this->itemTable = new DbTable\Vehicle();
        $this->itemLanguageTable = new DbTable\Vehicle\Language();
    }

    private function getOwnVehiclesAndEnginesCount($categoryId)
    {
        $db = $this->itemTable->getAdapter();

        //TODO: group by cars.id
        $select = $db->select()
            ->from('cars', new Zend_Db_Expr('COUNT(1)'))
            ->where('cars.item_type_id IN (?)', [
                DbTable\Item\Type::ENGINE,
                DbTable\Item\Type::VEHICLE
            ])
            ->where('not cars.is_group')
            ->join('car_parent', 'cars.id = car_parent.car_id', null)
            ->where('car_parent.parent_id = ?', $categoryId);

        return $db->fetchOne($select);
    }

    public function indexAction()
    {
        $language = $this->language();

        $key = 'CATEGORY_INDEX48_' . $language;

        $categories = $this->cache->getItem($key, $success);
        if (! $success) {
            $categories = [];

            $rows = $this->itemTable->fetchAll(
                $this->itemTable->select(true)
                    ->where('item_type_id = ?', DbTable\Item\Type::CATEGORY)
                    ->joinLeft('car_parent', 'cars.id = car_parent.car_id', null)
                    ->where('car_parent.parent_id is null')
                    ->order('name')
            );

            foreach ($rows as $row) {
                $langRow = $this->itemLanguageTable->fetchRow([
                    'language = ?' => $language,
                    'car_id = ?'   => $row->id
                ]);

                $carsCount = $this->itemTable->getVehiclesAndEnginesCount($row->id);

                $categories[] = [
                    'id'             => $row->id,
                    'url'            => $this->url()->fromRoute('categories', [
                        'action'           => 'category',
                        'category_catname' => $row->catname,
                    ]),
                    'name'           => $langRow ? $langRow->name : $row->name,
                    'short_name'     => $langRow ? $langRow->name : $row->name,//$langRow ? $langRow->short_name : $row->short_name,
                    'cars_count'     => $carsCount,
                    'new_cars_count' => $carsCount //$row->getWeekCarsCount(),
                ];
            }

            $this->cache->setItem($key, $categories);
        }

        $pictureTable = $this->catalogue()->getPictureTable();
        foreach ($categories as &$category) {
            $picture = $pictureTable->fetchRow(
                $pictureTable->select(true)
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->where('pictures.status IN (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
                    ->where('item_parent_cache.parent_id = ?', $category['id'])
                    ->order([
                        new Zend_Db_Expr('picture_item.perspective_id = 7 DESC'),
                        new Zend_Db_Expr('picture_item.perspective_id = 8 DESC'),
                        new Zend_Db_Expr('picture_item.perspective_id = 1 DESC')
                    ])
                    ->limit(1)
            );

            $image = null;
            if ($picture) {
                $image = $this->imageStorage()->getFormatedImage($picture->getFormatRequest(), 'picture-thumb');
            }

            $category['top_picture'] = [
                'image' => $image
            ];
        }

        return [
            'categories' => $categories
        ];
    }

    private function categoriesMenuActive(&$menu, $currentCategory, $isOther)
    {
        $activeFound = false;
        foreach ($menu as &$item) {
            $item['active'] = false;

            if (($item['isOther'] ? $isOther : ! $isOther) && ($item['id'] == $currentCategory->id)) {
                $activeFound = true;
                $item['active'] = true;
            }
            if ($this->categoriesMenuActive($item['categories'], $currentCategory, $isOther)) {
                $activeFound = true;
                $item['active'] = true;
            }
        }

        return $activeFound;
    }

    private function categoriesMenu($parent, $language, $maxDeep)
    {
        $categories = [];

        if ($maxDeep > 0) {

            $select = $this->itemTable->select(true)
                ->where('cars.item_type_id = ?', DbTable\Item\Type::CATEGORY)
                ->order($this->catalogue()->carsOrdering());

            if ($parent) {
                $select
                    ->join('car_parent', 'cars.id = car_parent.car_id', null)
                    ->where('car_parent.parent_id = ?', $parent->id);
            } else {
                $select
                    ->joinLeft('car_parent', 'cars.id = car_parent.car_id', null)
                    ->where('car_parent.parent_id IS NULL');
            }

            $rows = $this->itemTable->fetchAll($select);
            foreach ($rows as $row) {
                $langRow = $this->itemLanguageTable->fetchRow([
                    'language = ?' => $language,
                    'car_id = ?'   => $row->id
                ]);

                $carsCount = $this->itemTable->getVehiclesAndEnginesCount($row->id);

                $category = [
                    'id'             => $row->id,
                    'url'            => $this->url()->fromRoute('categories', [
                        'action'           => 'category',
                        'category_catname' => $row->catname,
                        'other'            => false,
                        'page'             => null
                    ]),
                    'name'           => $langRow ? $langRow->name : $row->name,
                    'short_name'     => $langRow ? $langRow->name : $row->name,//$langRow ? $langRow->short_name : $row->short_name,
                    'cars_count'     => $carsCount,
                    'new_cars_count' => 0,
                    'categories'     => $this->categoriesMenu($row, $language, $maxDeep - 1),
                    'isOther'        => false
                ];

                $categories[] = $category;
            }

            if ($parent && count($categories)) {
                $ownCarsCount = $this->getOwnVehiclesAndEnginesCount($parent->id);
                if ($ownCarsCount > 0) {
                    $categories[] = [
                        'id'             => $parent->id,
                        'url'            => $this->url()->fromRoute('categories', [
                            'action'           => 'category',
                            'category_catname' => $parent->catname,
                            'other'            => true,
                            'page'             => null
                        ]),
                        'short_name'     => $this->otherCategoryName,
                        'cars_count'     => $ownCarsCount,
                        'new_cars_count' => 0, //$parent->getWeekOwnCarsCount(),
                        'isOther'        => true,
                        'categories'     => []
                    ];
                }
            }
        }

        usort($categories, function ($a, $b) {
            if ($a["short_name"] == $this->otherCategoryName) {
                return 1;
            }
            if ($b["short_name"] == $this->otherCategoryName) {
                return -1;
            }
            return strcmp($a["short_name"], $b["short_name"]);
        });

        return $categories;
    }

    private function doCategoryAction($callback)
    {
        $language = $this->language();

        $currentCategory = $this->itemTable->fetchRow([
            'catname = ?' => (string)$this->params('category_catname')
        ]);
        $isOther = (bool)$this->params('other');

        if (! $currentCategory) {
            return $this->notFoundAction();
        }

        $categoryLang = $this->itemLanguageTable->fetchRow([
            'language = ?' => $language,
            'car_id = ?'   => $currentCategory->id
        ]);

        $breadcrumbs = [[
            'name' => $categoryLang && $categoryLang->name ? $categoryLang->name : $currentCategory->name,
            'url'  => $this->url()->fromRoute('categories', [
                'action'           => 'category',
                'category_catname' => $currentCategory->catname,
                'other'            => false,
                'path'             => [],
                'page'             => 1
            ])
        ]];

        $topCategory = $currentCategory;

        while (true) {
            $parentCategory =$this->itemTable->fetchRow(
                $this->itemTable->select(true)
                    ->join('car_parent', 'cars.id = car_parent.parent_id', null)
                    ->where('car_parent.car_id = ?', $topCategory->id)
            );
            if (!$parentCategory) {
                break;
            }
            
            $topCategory = $parentCategory;

            $categoryLang = $this->itemLanguageTable->fetchRow([
                'language = ?' => $language,
                'car_id = ?'   => $parentCategory->id
            ]);

            $name = $categoryLang && $categoryLang->name // short_name
                ? $categoryLang->name // short_name
                : $parentCategory->name; // short_name

            array_unshift($breadcrumbs, [
                'name' => $name,
                'url'  => $this->url()->fromRoute('categories', [
                    'action'           => 'category',
                    'category_catname' => $parentCategory->catname,
                    'other'            => false,
                    'path'             => [],
                    'page'             => 1
                ])
            ]);
        }

        $categoryLang = $this->itemLanguageTable->fetchRow([
            'language = ?' => $language,
            'car_id = ?'   => $currentCategory->id
        ]);

        $path = $this->params('path');
        $path = $path ? (array)$path : [];

        $currentCar = $currentCategory;

        $breadcrumbsPath = [];

        foreach ($path as $pathNode) {
            $childCar = $this->itemTable->fetchRow(
                $this->itemTable->select(true)
                    ->join('car_parent', 'cars.id = car_parent.car_id', null)
                    ->where('car_parent.parent_id = ?', $currentCar->id)
                    ->where('car_parent.catname = ?', $pathNode)
            );

            if (! $childCar) {
                return $this->notFoundAction();
            }

            $breadcrumbsPath[] = $pathNode;

            $breadcrumbs[] = [
                'name' => $this->car()->formatName($childCar, $language),
                'url'  => $this->url()->fromRoute('categories', [
                    'action'           => 'category',
                    'category_catname' => $currentCategory->catname,
                    'other'            => false,
                    'path'             => $breadcrumbsPath,
                    'page'             => 1
                ])
            ];

            $currentCar = $childCar;
        }

        $key = 'CATEGORY_MENU333_' . $topCategory->id . '_' . $language;

        $menu = $this->cache->getItem($key, $success);
        if (! $success) {
            $menu = $this->categoriesMenu($topCategory, $language, 2);

            $this->cache->setItem($key, $menu);
        }

        $this->categoriesMenuActive($menu, $currentCategory, $isOther);

        $sideBarModel = new ViewModel([
            'categories' => $menu,
            'category'   => $currentCategory,
            'isOther'    => $isOther,
            'deep'       => 1
        ]);
        $sideBarModel->setTemplate('application/category/menu');
        $this->layout()->addChild($sideBarModel, 'sidebar');

        $data = [
            'category'     => $currentCategory,
            'categoryLang' => $categoryLang,
            'isOther'      => $isOther,
            'currentItem'  => $currentCar ? $currentCar : $currentCategory
        ];
        

        $result = $callback($language, $topCategory, $currentCategory,
            $categoryLang, $isOther, $path, $currentCar, $breadcrumbs);

        if (is_array($result)) {
            return array_replace($data, $result);
        }

        return $result;
    }

    public function categoryAction()
    {
        return $this->doCategoryAction(function (
            $language,
            $topCategory,
            $currentCategory,
            $categoryLang,
            $isOther,
            $path,
            $currentCar,
            $breadcrumbs
        ) {

            $haveSubcategories = (bool)$this->itemTable->fetchRow(
                $this->itemTable->select(true)
                    ->where('cars.item_type_id = ?', DbTable\Item\Type::CATEGORY)
                    ->join('car_parent', 'cars.id = car_parent.car_id', null)
                    ->where('car_parent.parent_id = ?', $currentCategory->id)
            );
            
            $select = $this->itemTable->select(true)
                ->join('car_parent', 'cars.id = car_parent.car_id', null)
                ->order($this->catalogue()->carsOrdering())
                ->where('car_parent.parent_id = ?', $currentCar ? $currentCar->id : $currentCategory->id);
            
            if ($isOther) {
                $select->where('cars.item_type_id <> ?', DbTable\Item\Type::CATEGORY);
            } else {
                if ($haveSubcategories) {
                    $select->where('cars.item_type_id = ?', DbTable\Item\Type::CATEGORY);
                }
            }
            
            $paginator = new \Zend\Paginator\Paginator(
                new Zend1DbTableSelect($select)
            );

            $paginator
                ->setItemCountPerPage($this->catalogue()->getCarsPerPage())
                ->setCurrentPageNumber($this->params('page'));

            $users = new User();
            $contributors = [];
            /*$contributors = $users->fetchAll(
                $users->select(true)
                    ->join('category_item', 'users.id = category_item.user_id', null)
                    ->join('category_parent', 'category_item.category_id = category_parent.category_id', null)
                    ->where('category_parent.parent_id = ?', $currentCategory->id)
                    ->where('not users.deleted')
                    ->group('users.id')
            );*/

            $title = '';
            if ($currentCar) {
                $title = $this->car()->formatName($currentCar, $language);
            } else {
                $title = $categoryLang ? $categoryLang->name : $currentCategory->name;
            }
            
            $itemParentTable = new VehicleParent();
            
            $listBuilder = new \Application\Model\Item\ListBuilder\Category([
                'catalogue'       => $this->catalogue(),
                'router'          => $this->getEvent()->getRouter(),
                'picHelper'       => $this->getPluginManager()->get('pic'),
                'itemParentTable' => $itemParentTable,
                'currentItem'     => $currentCar,
                'category'        => $currentCategory,
                'isOther'         => $isOther,
                'path'            => $path
            ]);
            
            if ($currentCar && $paginator->getTotalItemCount() <= 0) {
                $select = $this->itemTable->select(true)
                    ->where('cars.id = ?', $currentCar->id);
                
                $paginator = new \Zend\Paginator\Paginator(
                    new Zend1DbTableSelect($select)
                );
                
                $cPath = $path;
                $catname = array_pop($cPath);
                
                $parentItemRow = $this->itemTable->fetchRow(
                    $this->itemTable->select(true)
                        ->join('car_parent', 'cars.id = car_parent.parent_id', null)
                        ->where('car_parent.car_id = ?', $currentCar->id)
                        ->where('car_parent.catname = ?', $catname)
                );
                
                $listBuilder
                    ->setPath($cPath)
                    ->setCurrentItem($parentItemRow);
            }

            $listData = $this->car()->listData($paginator->getCurrentItems(), [
                'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([]),
                'useFrontPictures' => $haveSubcategories,
                'disableLargePictures' => true,
                'picturesDateSort' => true,
                'listBuilder' => $listBuilder
            ]);

            $description = null;
            if ($categoryLang['text_id']) {
                $description = $this->textStorage->getText($categoryLang['text_id']);
            }
            
            $otherPictures = [];
            $otherItemsCount = 0;
            $isLastPage = $paginator->getCurrentPageNumber() == $paginator->count();
            if ($haveSubcategories && $isLastPage && ! $currentCar && ! $isOther) {

                $select = $this->itemTable->select(true)
                    ->where('cars.item_type_id IN (?)', [
                        DbTable\Item\Type::ENGINE,
                        DbTable\Item\Type::VEHICLE
                    ])
                    ->join('car_parent', 'cars.id = car_parent.car_id', null)
                    ->where('car_parent.parent_id = ?', $currentCategory->id);
                    
                $otherPaginator = new \Zend\Paginator\Paginator(
                    new Zend1DbTableSelect($select)
                );
                
                $otherItemsCount = $otherPaginator->getTotalItemCount();
                
                $pictureTable = new Picture();
                $pictureRows = $pictureTable->fetchAll(
                    $pictureTable->select(true)
                        ->where('pictures.status IN (?)', [
                            Picture::STATUS_NEW, Picture::STATUS_ACCEPTED
                        ])
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                        ->join('cars', 'picture_item.item_id = cars.id', null)
                        ->where('cars.item_type_id IN (?)', [
                            DbTable\Item\Type::ENGINE,
                            DbTable\Item\Type::VEHICLE
                        ])
                        ->join('car_parent', 'cars.id = car_parent.car_id', null)
                        ->where('car_parent.parent_id = ?', $currentCategory->id)
                        ->order($this->catalogue()->picturesOrdering())
                        ->limit(4)
                );
                
                $imageStorage = $this->imageStorage();
                foreach ($pictureRows as $pictureRow) {
                    $imageInfo = $imageStorage->getFormatedImage($pictureRow->getFormatRequest(), 'picture-thumb');
                    
                    $otherPictures[] = [
                        'name' => $this->pic()->name($pictureRow, $language),
                        'src'  => $imageInfo ? $imageInfo->getSrc() : null,
                        'url'  => $this->url()->fromRoute('categories', [
                            'action'           => 'category',
                            'category_catname' => $currentCategory['catname'],
                            'other'            => true,
                            'picture_id'       => $pictureRow['identity'] ? $pictureRow['identity'] : $pictureRow['id']
                        ], [], true)
                    ];
                }
            }

            return [
                'title'            => $title,
                'breadcrumbs'      => $breadcrumbs,
                'paginator'        => $paginator,
                'contributors'     => $contributors,
                'listData'         => $listData,
                'urlParams'        => [
                    'action'           => 'category',
                    'category_catname' => $currentCategory->catname,
                    'other'            => $isOther,
                    'path'             => $path
                ],
                'description'     => $description,
                'otherItemsCount' => $otherItemsCount,
                'otherPictures'   => $otherPictures,
                'otherCategoryName' => $this->otherCategoryName
            ];
        });
    }

    public function categoryPicturesAction()
    {
        return $this->doCategoryAction(function (
            $language,
            $topCategory,
            $currentCategory,
            $categoryLang,
            $isOther,
            $path,
            $currentCar,
            $breadcrumbs
        ) {

            $pictureTable = $this->catalogue()->getPictureTable();

            $select = $pictureTable->select(true)
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->where('pictures.status IN (?)', [
                    Picture::STATUS_NEW, Picture::STATUS_ACCEPTED
                ])
                ->order($this->catalogue()->picturesOrdering())
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $currentCar ? $currentCar->id : $currentCategory->id)
                ->group('pictures.id');

            $paginator = new \Zend\Paginator\Paginator(
                new Zend1DbTableSelect($select)
            );

            $paginator
                ->setItemCountPerPage($this->catalogue()->getPicturesPerPage())
                ->setCurrentPageNumber($this->params('page'));

            $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

            $picturesData = $this->pic()->listData($select, [
                'width' => 4,
                'url'   => function ($picture) use ($currentCategory, $isOther, $path) {
                    return $this->url()->fromRoute('categories', [
                        'action'           => 'category-picture',
                        'category_catname' => $currentCategory->catname,
                        'other'            => $isOther,
                        'path'             => $path,
                        'picture_id'       => $picture['identity'] ? $picture['identity'] : $picture['id']
                    ]);
                }
            ]);

            return [
                'breadcrumbs'  => $breadcrumbs,
                'paginator'    => $paginator,
                'picturesData' => $picturesData,
            ];
        });
    }

    public function categoryPictureAction()
    {
        return $this->doCategoryAction(function (
            $language,
            $topCategory,
            $currentCategory,
            $categoryLang,
            $isOther,
            $path,
            $currentCar,
            $breadcrumbs
        ) {

            $pictureTable = $this->catalogue()->getPictureTable();

            $select = $pictureTable->select(true)
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->where('pictures.status IN (?)', [
                    Picture::STATUS_NEW, Picture::STATUS_ACCEPTED
                ])
                ->order($this->catalogue()->picturesOrdering())
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $currentCar ? $currentCar->id : $currentCategory->id);

            $selectRow = clone $select;

            $pictureId = (string)$this->params('picture_id');

            $selectRow
                ->where('pictures.id = ?', $pictureId)
                ->where('pictures.identity IS NULL');

            $picture = $selectRow->getTable()->fetchRow($selectRow);

            if (! $picture) {
                $selectRow = clone $select;

                $selectRow->where('pictures.identity = ?', $pictureId);

                $picture = $selectRow->getTable()->fetchRow($selectRow);
            }

            if (! $picture) {
                return $this->notFoundAction();
            }

            return [
                'breadcrumbs' => $breadcrumbs,
                'picture'     => array_replace(
                    $this->pic()->picPageData($picture, $select, []),
                    [
                        'gallery2'   => true,
                        'galleryUrl' => $this->url()->fromRoute('categories', [
                            'action'           => 'category-picture-gallery',
                            'category_catname' => $currentCategory->catname,
                            'other'            => $isOther,
                            'path'             => $path,
                            'picture_id'       => $picture['identity'] ? $picture['identity'] : $picture['id']
                        ])
                    ]
                )
            ];
        });
    }

    public function categoryPictureGalleryAction()
    {
        return $this->doCategoryAction(function (
            $language,
            $topCategory,
            $currentCategory,
            $categoryLang,
            $isOther,
            $path,
            $currentCar,
            $breadcrumbs
        ) {

            $pictureTable = $this->catalogue()->getPictureTable();

            $select = $pictureTable->select(true)
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->where('pictures.status IN (?)', [
                    Picture::STATUS_NEW, Picture::STATUS_ACCEPTED
                ])
                ->order($this->catalogue()->picturesOrdering())
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->group('pictures.id')
                ->where('item_parent_cache.parent_id = ?', $currentCar ? $currentCar->id : $currentCategory->id);

            $selectRow = clone $select;

            $pictureId = (string)$this->params('picture_id');

            $selectRow
                ->where('pictures.id = ?', $pictureId)
                ->where('pictures.identity IS NULL');

            $picture = $selectRow->getTable()->fetchRow($selectRow);

            if (! $picture) {
                $selectRow = clone $select;

                $selectRow->where('pictures.identity = ?', $pictureId);

                $picture = $selectRow->getTable()->fetchRow($selectRow);
            }

            if (! $picture) {
                return $this->notFoundAction();
            }

            return new JsonModel($this->pic()->gallery2($select, [
                'page'        => $this->params()->fromQuery('page'),
                'pictureId'   => $this->params()->fromQuery('pictureId'),
                'reuseParams' => true,
                'urlParams'   => [
                    'action' => 'category-picture'
                ]
            ]));
        });
    }
}
