<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;
use Autowp\User\Model\DbTable\User;

use Application\Model\Categories;
use Application\Model\DbTable;

use Zend_Db_Expr;

class CategoryController extends AbstractActionController
{
    private $cache;

    private $textStorage;

    /**
     * @var DbTable\Item
     */
    private $itemTable;

    /**
     * @var DbTable\Item\Language
     */
    private $itemLanguageTable;

    /**
     * @var Categories
     */
    private $categories;

    public function __construct($cache, $textStorage, Categories $categories)
    {
        $this->cache = $cache;
        $this->textStorage = $textStorage;
        $this->categories = $categories;

        $this->itemTable = new DbTable\Item();
        $this->itemLanguageTable = new DbTable\Item\Language();
    }

    private function getOwnVehiclesAndEnginesCount($categoryId)
    {
        $db = $this->itemTable->getAdapter();

        //TODO: group by item.id
        $select = $db->select()
            ->from('item', new Zend_Db_Expr('COUNT(1)'))
            ->where('item.item_type_id IN (?)', [
                DbTable\Item\Type::ENGINE,
                DbTable\Item\Type::VEHICLE
            ])
            ->where('not item.is_group')
            ->join('item_parent', 'item.id = item_parent.item_id', null)
            ->where('item_parent.parent_id = ?', $categoryId);

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
                    ->joinLeft('item_parent', 'item.id = item_parent.item_id', null)
                    ->where('item_parent.parent_id is null')
                    ->order('name')
            );

            foreach ($rows as $row) {
                $langRow = $this->itemLanguageTable->fetchRow([
                    'language = ?' => $language,
                    'item_id = ?'  => $row->id
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
                    ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
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

    private function categoriesMenuActive(&$menu, $categoryParentIds, $isOther)
    {
        $activeFound = false;
        foreach ($menu as &$item) {
            $item['active'] = false;

            if (($item['isOther'] ? $isOther : ! $isOther) && in_array($item['id'], $categoryParentIds)) {
                $activeFound = true;
                $item['active'] = true;
            }
            if ($this->categoriesMenuActive($item['categories'], $categoryParentIds, $isOther)) {
                $activeFound = true;
                $item['active'] = true;
            }
        }

        return $activeFound;
    }

    private function categoriesMenu($parent, $language, $maxDeep)
    {
        $categories = [];

        $otherCategoriesName = $this->translate('categories/other');

        if ($maxDeep > 0) {
            $db = $this->itemTable->getAdapter();

            $categories = $this->categories->getCategoriesList($parent['id'], $language, null, 'name');

            foreach ($categories as &$category) {
                $category['categories'] = $this->categoriesMenu($category, $language, $maxDeep - 1);
                $category['isOther'] = false;
            }
            unset($category); // prevent bugs

            if ($parent && count($categories)) {
                $ownCarsCount = $this->getOwnVehiclesAndEnginesCount($parent['id']);
                if ($ownCarsCount > 0) {
                    $categories[] = [
                        'id'             => $parent['id'],
                        'url'            => $this->url()->fromRoute('categories', [
                            'action'           => 'category',
                            'category_catname' => $parent['catname'],
                            'other'            => true,
                            'page'             => null
                        ]),
                        'short_name'     => $otherCategoriesName,
                        'cars_count'     => $ownCarsCount,
                        'new_cars_count' => 0, //$parent->getWeekOwnCarsCount(),
                        'isOther'        => true,
                        'categories'     => []
                    ];
                }
            }
        }

        usort($categories, function ($a, $b) use ($otherCategoriesName) {
            if ($a["short_name"] == $otherCategoriesName) {
                return 1;
            }
            if ($b["short_name"] == $otherCategoriesName) {
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
            'item_id = ?'   => $currentCategory->id
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
            $parentCategory = $this->itemTable->fetchRow(
                $this->itemTable->select(true)
                    ->join('item_parent', 'item.id = item_parent.parent_id', null)
                    ->where('item_parent.item_id = ?', $topCategory->id)
            );
            if (! $parentCategory) {
                break;
            }

            $topCategory = $parentCategory;

            $categoryLang = $this->itemLanguageTable->fetchRow([
                'language = ?' => $language,
                'item_id = ?'  => $parentCategory->id
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
            'item_id = ?'  => $currentCategory->id
        ]);

        $path = $this->params('path');
        $path = $path ? (array)$path : [];

        $currentCar = $currentCategory;

        $breadcrumbsPath = [];

        foreach ($path as $pathNode) {
            $childCar = $this->itemTable->fetchRow(
                $this->itemTable->select(true)
                    ->join('item_parent', 'item.id = item_parent.item_id', null)
                    ->where('item_parent.parent_id = ?', $currentCar->id)
                    ->where('item_parent.catname = ?', $pathNode)
            );

            if (! $childCar) {
                return $this->notFoundAction();
            }

            $breadcrumbsPath[] = $pathNode;

            $breadcrumbs[] = [
                'name' => $this->car()->formatName($childCar, $language),
                'url'  => $this->url()->fromRoute('categories', [
                    'action'           => 'category',
                    'category_catname' => $currentCategory['catname'],
                    'other'            => false,
                    'path'             => $breadcrumbsPath,
                    'page'             => 1
                ])
            ];

            $currentCar = $childCar;
        }

        $key = 'CATEGORY_MENU344_' . $topCategory->id . '_' . $language;

        $menu = $this->cache->getItem($key, $success);
        if (! $success) {
            $menu = $this->categoriesMenu($topCategory, $language, 2);

            $this->cache->setItem($key, $menu);
        }

        $db = $this->itemTable->getAdapter();
        $categoryParentIds = $db->fetchCol(
            $db->select()
                ->from('item_parent_cache', 'parent_id')
                ->where('item_id = ?', $currentCategory['id'])
        );

        $this->categoriesMenuActive($menu, $categoryParentIds, $isOther);

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
                    ->where('item.item_type_id = ?', DbTable\Item\Type::CATEGORY)
                    ->join('item_parent', 'item.id = item_parent.item_id', null)
                    ->where('item_parent.parent_id = ?', $currentCategory->id)
            );

            $select = $this->itemTable->select(true)
                ->join('item_parent', 'item.id = item_parent.item_id', null)
                ->order($this->catalogue()->itemOrdering())
                ->where('item_parent.parent_id = ?', $currentCar ? $currentCar->id : $currentCategory->id);

            if ($isOther) {
                $select->where('item.item_type_id <> ?', DbTable\Item\Type::CATEGORY);
            } else {
                if ($haveSubcategories) {
                    $select->where('item.item_type_id = ?', DbTable\Item\Type::CATEGORY);
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

            $itemParentTable = new DbTable\Item\ParentTable();

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
                if ($path) {
                    $select = $this->itemTable->select(true)
                        ->where('item.id = ?', $currentCar->id);

                    $paginator = new \Zend\Paginator\Paginator(
                        new Zend1DbTableSelect($select)
                    );

                    $cPath = $path;
                    $catname = array_pop($cPath);

                    $parentItemRow = $this->itemTable->fetchRow(
                        $this->itemTable->select(true)
                            ->join('item_parent', 'item.id = item_parent.parent_id', null)
                            ->where('item_parent.item_id = ?', $currentCar->id)
                            ->where('item_parent.catname = ?', $catname)
                    );

                    $listBuilder
                        ->setPath($cPath)
                        ->setCurrentItem($parentItemRow);
                }
            }

            $listData = $this->car()->listData($paginator->getCurrentItems(), [
                'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([]),
                'useFrontPictures' => $haveSubcategories,
                'disableLargePictures' => true,
                'picturesDateSort' => true,
                'listBuilder' => $listBuilder
            ]);

            /*$description = null;
            if ($categoryLang['text_id']) {
                $description = $this->textStorage->getText($categoryLang['text_id']);
            }*/

            $itemLanguageTable = new DbTable\Item\Language();
            $db = $itemLanguageTable->getAdapter();
            $orderExpr = $db->quoteInto('language = ? desc', $this->language());
            $itemLanguageRows = $itemLanguageTable->fetchAll([
                'item_id = ?' => $currentCategory['id']
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

            $otherPictures = [];
            $otherItemsCount = 0;
            $isLastPage = $paginator->getCurrentPageNumber() == $paginator->count();
            $isCategory = $currentCar->item_type_id == DbTable\Item\Type::CATEGORY;

            if ($haveSubcategories && $isLastPage && $isCategory && ! $isOther) {
                $select = $this->itemTable->select(true)
                    ->where('item.item_type_id IN (?)', [
                        DbTable\Item\Type::ENGINE,
                        DbTable\Item\Type::VEHICLE
                    ])
                    ->join('item_parent', 'item.id = item_parent.item_id', null)
                    ->where('item_parent.parent_id = ?', $currentCategory->id);

                $otherPaginator = new \Zend\Paginator\Paginator(
                    new Zend1DbTableSelect($select)
                );

                $otherItemsCount = $otherPaginator->getTotalItemCount();

                $pictureTable = new DbTable\Picture();
                $pictureRows = $pictureTable->fetchAll(
                    $pictureTable->select(true)
                        ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                        ->join('item', 'picture_item.item_id = item.id', null)
                        ->where('item.item_type_id IN (?)', [
                            DbTable\Item\Type::ENGINE,
                            DbTable\Item\Type::VEHICLE
                        ])
                        ->join('item_parent', 'item.id = item_parent.item_id', null)
                        ->where('item_parent.parent_id = ?', $currentCategory->id)
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
                            'picture_id'       => $pictureRow['identity']
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
                'otherCategoryName' => $this->translate('categories/other')
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
                ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
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
                        'picture_id'       => $picture['identity']
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
                ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
                ->order($this->catalogue()->picturesOrdering())
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $currentCar ? $currentCar->id : $currentCategory->id);

            $selectRow = clone $select;

            $pictureId = (string)$this->params('picture_id');

            $selectRow->where('pictures.identity = ?', $pictureId);

            $picture = $selectRow->getTable()->fetchRow($selectRow);

            if (! $picture) {
                return $this->notFoundAction();
            }

            return [
                'breadcrumbs' => $breadcrumbs,
                'picture'     => array_replace(
                    $this->pic()->picPageData($picture, $select, []),
                    [
                        'galleryUrl' => $this->url()->fromRoute('categories', [
                            'action'           => 'category-picture-gallery',
                            'category_catname' => $currentCategory->catname,
                            'other'            => $isOther,
                            'path'             => $path,
                            'picture_id'       => $picture['identity']
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
                ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
                ->order($this->catalogue()->picturesOrdering())
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->group('pictures.id')
                ->where('item_parent_cache.parent_id = ?', $currentCar ? $currentCar->id : $currentCategory->id);

            $selectRow = clone $select;

            $pictureId = (string)$this->params('picture_id');

            $selectRow->where('pictures.identity = ?', $pictureId);

            $picture = $selectRow->getTable()->fetchRow($selectRow);

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

    public function newcarsAction()
    {
        $itemTable = new DbTable\Item();

        $category = $itemTable->fetchRow([
            'item_type_id = ?' => DbTable\Item\Type::CATEGORY,
            'id = ?'           => (int)$this->params('item_id')
        ]);
        if (! $category) {
            return $this->notFoundAction();
        }

        $language = $this->language();
        $itemLangTable = new DbTable\Item\Language();
        $itemLang = $itemLangTable->fetchRow([
            'item_id = ?'  => $category->id,
            'language = ?' => $language
        ]);


        $rows = $itemTable->fetchAll(
            $itemTable->select(true)
                ->where('item.item_type_id IN (?)', [
                    DbTable\Item\Type::VEHICLE,
                    DbTable\Item\Type::ENGINE
                ])
                ->join('item_parent', 'item.id = item_parent.item_id', null)
                ->join(['low_cat' => 'item'], 'item_parent.parent_id = low_cat.id', null)
                ->where('low_cat.item_type_id = ?', DbTable\Item\Type::CATEGORY)
                ->join('item_parent_cache', 'low_cat.id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $category->id)
                ->where('item_parent.timestamp > DATE_SUB(NOW(), INTERVAL ? DAY)', Categories::NEW_DAYS)
                ->group('item.id')
                ->order(['item_parent.timestamp DESC'])
                ->limit(20)
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = $row->getNameData($language);
        }

        $viewModel = new ViewModel([
            'items' => $items
        ]);
        $viewModel->setTerminal(true);

        return $viewModel;
    }
}
