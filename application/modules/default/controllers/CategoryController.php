<?php

class CategoryController extends Zend_Controller_Action
{
    private $otherCategoryName = 'Other';

    /**
     * @var Category
     */
    private $categoryTable;

    /**
     * @var Category_Language
     */
    private $categoryLanguageTable;

    function init()
    {
        parent::init();

        $this->categoryTable = new Category();
        $this->categoryLanguageTable = new Category_Language();
    }

    public function indexAction()
    {
        $language = $this->_helper->language();

        $cache = $this->getInvokeArg('bootstrap')
            ->getResource('cachemanager')->getCache('long');

        $key = 'CATEGORY_INDEX45_' . $language;

        if (!($categories = $cache->load($key))) {

            $categories = array();

            $rows = $this->categoryTable->fetchAll(array(
                'parent_id is null',
            ), 'short_name');

            foreach ($rows as $row) {

                $langRow = $this->categoryLanguageTable->fetchRow(array(
                    'language = ?'    => $language,
                    'category_id = ?' => $row->id
                ));

                $categories[] = array(
                    'id'             => $row->id,
                    'url'            => $this->_helper->url->url(array(
                        'controller'       => 'category',
                        'action'           => 'category',
                        'category_catname' => $row->catname,
                    ), 'category', true),
                    'name'           => $langRow ? $langRow->name : $row->name,
                    'short_name'     => $langRow ? $langRow->short_name : $row->short_name,
                    'cars_count'     => $row->getCarsCount(),
                    'new_cars_count' => $row->getWeekCarsCount(),
                );
            }

            $cache->save($categories, null, array(), 1800);
        }

        $pictures = $this->_helper->catalogue()->getPictureTable();
        foreach ($categories as &$category) {
            $category['top_picture'] = $pictures->fetchRow(
                $pictures->select(true)
                    ->join('category_car', 'pictures.car_id=category_car.car_id', null)
                    ->join('category_parent', 'category_car.category_id = category_parent.category_id', null)
                    ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                    ->where('pictures.status IN (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
                    ->where('category_parent.parent_id = ?', $category['id'])
                    ->order(array(
                        new Zend_Db_Expr('pictures.perspective_id = 7 DESC'),
                        new Zend_Db_Expr('pictures.perspective_id = 8 DESC'),
                        new Zend_Db_Expr('pictures.perspective_id = 1 DESC')
                    ))
                    ->limit(1)
            );
        }

        $this->view->assign(array(
            'categories'    => $categories
        ));
    }

    private function _categoriesMenuActive(&$menu, $currentCategory, $isOther)
    {
        $activeFound = false;
        foreach ($menu as &$item) {
            $item['active'] = false;

            if (($item['isOther'] ? $isOther : !$isOther) && ($item['id'] == $currentCategory->id)) {
                $activeFound = true;
                $item['active'] = true;
            }
            if ($this->_categoriesMenuActive($item['categories'], $currentCategory, $isOther)) {
                $activeFound = true;
                $item['active'] = true;
            }
        }

        return $activeFound;
    }

    private function _categoriesMenu($parent, $language, $maxDeep)
    {
        $categories = array();

        if ($maxDeep > 0) {
            $filter = $parent ? array(
                'parent_id = ?' => $parent->id
            ) : array(
                'parent_id is null'
            );
            $rows = $this->categoryTable->fetchAll($filter, 'short_name');
            foreach ($rows as $row) {
                $langRow = $this->categoryLanguageTable->fetchRow(array(
                    'language = ?'    => $language,
                    'category_id = ?' => $row['id']
                ));

                $category = array(
                    'id'             => $row->id,
                    'url'            => $this->_helper->url->url(array(
                        'controller'       => 'category',
                        'action'           => 'category',
                        'category_catname' => $row->catname,
                        'other'            => false,
                        'car_id'           => null,
                        'page'             => null
                    ), 'category', true),
                    'name'           => $langRow ? $langRow->name : $row->name,
                    'short_name'     => $langRow ? $langRow->short_name : $row->short_name,
                    'cars_count'     => $row->getCarsCount(),
                    'new_cars_count' => $row->getWeekCarsCount(),
                    'categories'     => $this->_categoriesMenu($row, $language, $maxDeep-1),
                    'isOther'        => false
                );

                $categories[] = $category;
            }

            if ($parent && count($categories)) {
                $ownCarsCount = $parent->getOwnCarsCount();
                if ($ownCarsCount > 0) {
                    $categories[] = array(
                        'id'             => $parent->id,
                        'url'            => $this->_helper->url->url(array(
                            'controller'       => 'category',
                            'action'           => 'category',
                            'category_catname' => $parent->catname,
                            'other'            => true,
                            'car_id'           => null,
                            'page'             => null
                        ), 'category', true),
                        'short_name'     => $this->otherCategoryName,
                        'cars_count'     => $ownCarsCount,
                        'new_cars_count' => $parent->getWeekOwnCarsCount(),
                        'isOther'        => true,
                        'categories'     => array()
                    );
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

    private function _categoryAction($callback)
    {
        $language = $this->_helper->language();

        $currentCategory = $this->categoryTable->fetchRow(array(
            'catname = ?' => (string)$this->_getParam('category_catname')
        ));
        $isOther = (bool)$this->_getParam('other');

        if (!$currentCategory) {
            return $this->_forward('notfound', 'error');
        }

        $categoryLang = $this->categoryLanguageTable->fetchRow(array(
            'language = ?'    => $language,
            'category_id = ?' => $currentCategory->id
        ));

        $breadcrumbs = array(array(
            'name' => $categoryLang && $categoryLang->short_name ? $categoryLang->short_name : $currentCategory->name,
            'url'  => $this->_helper->url->url(array(
                'module'           => 'default',
                'controller'       => 'category',
                'action'           => 'category',
                'category_catname' => $currentCategory->catname,
                'other'            => false,
                'car_id'           => null,
                'path'             => array(),
                'page'             => 1
            ), 'category', true)
        ));

        $topCategory = $currentCategory;

        while ($parentCategory = $topCategory->findParentCategory()) {
            $topCategory = $parentCategory;

            $categoryLang = $this->categoryLanguageTable->fetchRow(array(
                'language = ?'    => $language,
                'category_id = ?' => $parentCategory->id
            ));

            array_unshift($breadcrumbs, array(
                'name' => $categoryLang && $categoryLang->short_name ? $categoryLang->short_name : $parentCategory->short_name,
                'url'  => $this->_helper->url->url(array(
                    'module'           => 'default',
                    'controller'       => 'category',
                    'action'           => 'category',
                    'category_catname' => $parentCategory->catname,
                    'other'            => false,
                    'car_id'           => null,
                    'path'             => array(),
                    'page'             => 1
                ), 'category', true)
            ));
        }

        $categoryLang = $this->categoryLanguageTable->fetchRow(array(
            'language = ?'    => $language,
            'category_id = ?' => $currentCategory->id
        ));

        $carTable = $this->_helper->catalogue()->getCarTable();
        $carParentTable = new Car_Parent();

        $carId = $this->_getParam('car_id');
        $topCar = false;
        $currentCar = false;
        if ($carId) {
            $topCar = $carTable->fetchRow(
                $carTable->select(true)
                    ->where('cars.id = ?', $carId)
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                    ->join('category_car', 'car_parent_cache.parent_id = category_car.car_id', null)
                    ->join('category_parent', 'category_car.category_id = category_parent.category_id', null)
                    ->where('category_parent.parent_id = ?', $currentCategory->id)
            );
        }

        $path = array();

        if ($topCar) {
            $path = $this->_getParam('path');
            $path = $path ? (array)$path : array();

            $breadcrumbs[] = array(
                'name' => $topCar->getFullName($language),
                'url'  => $this->_helper->url->url(array(
                    'module'           => 'default',
                    'controller'       => 'category',
                    'action'           => 'category',
                    'category_catname' => $currentCategory->catname,
                    'other'            => false,
                    'car_id'           => $topCar->id,
                    'path'             => array(),
                    'page'             => 1
                ), 'category', true)
            );

            $currentCar = $topCar;

            $breadcrumbsPath = array();

            foreach ($path as $pathNode) {
                $childCar = $carTable->fetchRow(
                    $carTable->select(true)
                        ->join('car_parent', 'cars.id = car_parent.car_id', null)
                        ->where('car_parent.parent_id = ?', $currentCar->id)
                        ->where('car_parent.catname = ?', $pathNode)
                );

                if (!$childCar) {
                    return $this->_forward('notfound', 'error');
                }

                $breadcrumbsPath[] = $pathNode;

                $breadcrumbs[] = array(
                    'name' => $childCar->getFullName($language),
                    'url'  => $this->_helper->url->url(array(
                        'module'           => 'default',
                        'controller'       => 'category',
                        'action'           => 'category',
                        'category_catname' => $currentCategory->catname,
                        'other'            => false,
                        'car_id'           => $topCar->id,
                        'path'             => $breadcrumbsPath,
                        'page'             => 1
                    ), 'category', true)
                );

                $currentCar = $childCar;
            }
        }

        $cache = $this->getInvokeArg('bootstrap')
            ->getResource('cachemanager')->getCache('long');

        $key = 'CATEGORY_MENU322_' . $topCategory->id . '_' . $language;

        if (!($menu = $cache->load($key))) {

            $menu = $this->_categoriesMenu($topCategory, $language, 3);

            $cache->save($menu, null, array(), 600);
        }

        $this->_categoriesMenuActive($menu, $currentCategory, $isOther);

        $this->view->assign(array(
            'category'     => $currentCategory,
            'categoryLang' => $categoryLang,
            'isOther'      => $isOther,
            'categories'   => $menu,
        ));

        return $callback($language, $topCategory, $currentCategory,
            $categoryLang, $isOther, $topCar, $path, $currentCar, $breadcrumbs);
    }

    public function categoryAction()
    {
        return $this->_categoryAction(function($language, $topCategory,
                $currentCategory, $categoryLang, $isOther, $topCar, $path,
                $currentCar, $breadcrumbs) {

            $carTable = $this->_helper->catalogue()->getCarTable();

            if ($topCar) {
                $select = $carTable->select(true)
                    ->join('car_parent', 'cars.id = car_parent.car_id', null)
                    ->where('car_parent.parent_id = ?', $currentCar->id)
                    ->order($this->_helper->catalogue()->carsOrdering());

            } else {

                $select = $carTable->select(true)
                    ->join('category_car', 'cars.id = category_car.car_id', null)
                    ->order($this->_helper->catalogue()->carsOrdering());

                if ($isOther) {
                    $select->where('category_car.category_id=?', $currentCategory->id);
                } else {
                    $select
                        ->join('category_parent', 'category_car.category_id=category_parent.category_id', null)
                        ->group('cars.id')
                        ->where('category_parent.parent_id = ?', $currentCategory->id);
                }
            }

            $paginator = Zend_Paginator::factory($select)
                ->setItemCountPerPage($this->_helper->catalogue()->getCarsPerPage())
                ->setCurrentPageNumber($this->_getParam('page'));

            $users = new Users();
            $contributors = $users->fetchAll(
                $users->select(true)
                    ->join('category_car', 'users.id = category_car.user_id', null)
                    ->join('category_parent', 'category_car.category_id = category_parent.category_id', null)
                    ->where('category_parent.parent_id = ?', $currentCategory->id)
                    ->where('not users.deleted')
                    ->group('users.id')
            );

            $title = '';
            if ($currentCategory) {
                if ($topCar) {
                    if ($currentCar) {
                        $title = $currentCar->getFullName($language);
                    } else {
                        $title = $topCar->getFullName($language);
                    }
                } else {
                    $title = $categoryLang ? $categoryLang->name : $currentCategory->name;
                }
            }

            $carParentTable = new Car_Parent();

            $this->view->assign(array(
                'title'            => $title,
                'breadcrumbs'      => $breadcrumbs,
                'paginator'        => $paginator,
                'contributors'     => $contributors,
                'listData'         => $this->_helper->car->listData($paginator->getCurrentItems(), array(
                    'picturesDateSort' => true,
                    'detailsUrl' => function($listCar) use ($topCar, $currentCar, $carParentTable, $currentCategory, $isOther, $path) {

                        $carParentAdapter = $carParentTable->getAdapter();
                        $hasChilds = (bool)$carParentAdapter->fetchOne(
                            $carParentAdapter->select()
                                ->from($carParentTable->info('name'), new Zend_Db_Expr('1'))
                                ->where('parent_id = ?', $listCar->id)
                        );

                        if (!$hasChilds) {
                            return false;
                        }

                        // found parent row
                        if ($currentCar) {
                            if (count($path)) {
                                $carParentRow = $carParentTable->fetchRow(array(
                                    'car_id = ?'    => $listCar->id,
                                    'parent_id = ?' => $currentCar->id
                                ));
                                if ($carParentRow) {
                                    $currentPath = array_merge($path, array(
                                        $carParentRow->catname
                                    ));
                                } else {
                                    $currentPath = false;
                                }
                            } else {
                                $carParentRow = $carParentTable->fetchRow(array(
                                    'car_id = ?'    => $listCar->id,
                                    'parent_id = ?' => $currentCar->id
                                ));
                                if ($carParentRow) {
                                    $currentPath = array_merge($path, array(
                                        $carParentRow->catname
                                    ));
                                } else {
                                    $currentPath = false;
                                }
                            }

                            if (!$currentPath) {
                                return false;
                            }
                        } else {
                            $currentPath = array();
                        }

                        $url = $this->_helper->url->url(array(
                            'module'           => 'default',
                            'controller'       => 'category',
                            'action'           => 'category',
                            'category_catname' => $currentCategory->catname,
                            'other'            => $isOther,
                            'car_id'           => $topCar ? $topCar->id : $listCar->id,
                            'path'             => $currentPath,
                            'page'             => 1
                        ), 'category', true);

                        return $url;
                    },
                    'allPicturesUrl' => function($listCar) use ($topCar, $currentCar, $carParentTable, $currentCategory, $isOther, $path) {

                        // found parent row
                        if ($currentCar) {
                            if (count($path)) {
                                $carParentRow = $carParentTable->fetchRow(array(
                                    'car_id = ?'    => $listCar->id,
                                    'parent_id = ?' => $currentCar->id
                                ));
                                if ($carParentRow) {
                                    $currentPath = array_merge($path, array(
                                        $carParentRow->catname
                                    ));
                                } else {
                                    $currentPath = false;
                                }
                            } else {
                                $carParentRow = $carParentTable->fetchRow(array(
                                    'car_id = ?'    => $listCar->id,
                                    'parent_id = ?' => $currentCar->id
                                ));
                                if ($carParentRow) {
                                    $currentPath = array_merge($path, array(
                                        $carParentRow->catname
                                    ));
                                } else {
                                    $currentPath = false;
                                }
                            }

                            if (!$currentPath) {
                                return false;
                            }
                        } else {
                            $currentPath = array();
                        }

                        $url = $this->_helper->url->url(array(
                            'module'           => 'default',
                            'controller'       => 'category',
                            'action'           => 'category-pictures',
                            'category_catname' => $currentCategory->catname,
                            'other'            => $isOther,
                            'car_id'           => $topCar ? $topCar->id : $listCar->id,
                            'path'             => $currentPath,
                            'page'             => 1
                        ), 'category', true);

                        return $url;
                    },
                    'pictureUrl'           => function($listCar, $picture) use ($currentCategory, $isOther, $topCar, $currentCar, $carParentTable, $path) {

                        // found parent row
                        if ($currentCar) {
                            if (count($path)) {
                                $carParentRow = $carParentTable->fetchRow(array(
                                    'car_id = ?'    => $listCar->id,
                                    'parent_id = ?' => $currentCar->id
                                ));
                                if ($carParentRow) {
                                    $currentPath = array_merge($path, array(
                                        $carParentRow->catname
                                    ));
                                } else {
                                    $currentPath = false;
                                }
                            } else {
                                $carParentRow = $carParentTable->fetchRow(array(
                                    'car_id = ?'    => $listCar->id,
                                    'parent_id = ?' => $currentCar->id
                                ));
                                if ($carParentRow) {
                                    $currentPath = array_merge($path, array(
                                        $carParentRow->catname
                                    ));
                                } else {
                                    $currentPath = false;
                                }
                            }

                            if (!$currentPath) {
                                return false;
                            }
                        } else {
                            $currentPath = array();
                        }

                        return $this->_helper->url->url(array(
                            'module'           => 'default',
                            'controller'       => 'category',
                            'action'           => 'category-picture',
                            'category_catname' => $currentCategory->catname,
                            'other'            => $isOther,
                            'car_id'           => $topCar ? $topCar->id : $listCar->id,
                            'path'             => $currentPath,
                            'picture_id'       => $picture['identity'] ? $picture['identity'] : $picture['id']
                        ));
                    }
                ))
            ));
        });
    }

    public function categoryPicturesAction()
    {
        return $this->_categoryAction(function($language, $topCategory,
                $currentCategory, $categoryLang, $isOther, $topCar, $path,
                $currentCar, $breadcrumbs) {

            $pictureTable = $this->_helper->catalogue()->getPictureTable();

            $select = $pictureTable->select(true)
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->where('pictures.status IN (?)', array(
                    Picture::STATUS_NEW, Picture::STATUS_ACCEPTED
                ))
                ->order($this->_helper->catalogue()->picturesOrdering());

            if ($topCar) {
                $select
                    ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                    ->where('car_parent_cache.parent_id = ?', $currentCar->id);

            } else {

                $select
                    ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                    ->join('category_car', 'car_parent_cache.parent_id = category_car.car_id', null);

                if ($isOther) {
                    $select->where('category_car.category_id=?', $currentCategory->id);
                } else {
                    $select
                        ->join('category_parent', 'category_car.category_id = category_parent.category_id', null)
                        ->group('pictures.id')
                        ->where('category_parent.parent_id = ?', $currentCategory->id);
                }
            }

            $paginator = Zend_Paginator::factory($select)
                ->setItemCountPerPage($this->_helper->catalogue()->getPicturesPerPage())
                ->setCurrentPageNumber($this->_getParam('page'));

            $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

            $picturesData = $this->_helper->pic->listData($select, array(
                'width' => 4,
                'url'   => function($picture) use ($currentCategory, $isOther, $topCar, $path) {
                    return $this->_helper->url->url(array(
                        'module'           => 'default',
                        'controller'       => 'category',
                        'action'           => 'category-picture',
                        'category_catname' => $currentCategory->catname,
                        'other'            => $isOther,
                        'car_id'           => $topCar ? $topCar->id : null,
                        'path'             => $path,
                        'picture_id'       => $picture['identity'] ? $picture['identity'] : $picture['id']
                    ));
                }
            ));

            $this->view->assign(array(
                'breadcrumbs'  => $breadcrumbs,
                'paginator'    => $paginator,
                'picturesData' => $picturesData,
            ));
        });
    }

    public function categoryPictureAction()
    {
        return $this->_categoryAction(function($language, $topCategory,
                $currentCategory, $categoryLang, $isOther, $topCar, $path,
                $currentCar, $breadcrumbs) {

            $pictureTable = $this->_helper->catalogue()->getPictureTable();

            $select = $pictureTable->select(true)
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->where('pictures.status IN (?)', array(
                    Picture::STATUS_NEW, Picture::STATUS_ACCEPTED
                ))
                ->order($this->_helper->catalogue()->picturesOrdering());

            if ($topCar) {
                $select
                    ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                    ->where('car_parent_cache.parent_id = ?', $currentCar->id);

            } else {

                $select
                    ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                    ->join('category_car', 'car_parent_cache.parent_id = category_car.car_id', null);

                if ($isOther) {
                    $select->where('category_car.category_id = ?', $currentCategory->id);
                } else {
                    $select
                        ->join('category_parent', 'category_car.category_id = category_parent.category_id', null)
                        ->group('pictures.id')
                        ->where('category_parent.parent_id = ?', $currentCategory->id);
                }
            }

            $selectRow = clone $select;

            $pictureId = (string)$this->_getParam('picture_id');

            $selectRow
                ->where('pictures.id = ?', $pictureId)
                ->where('pictures.identity IS NULL');

            $picture = $selectRow->getTable()->fetchRow($selectRow);

            if (!$picture) {
                $selectRow = clone $select;

                $selectRow->where('pictures.identity = ?', $pictureId);

                $picture = $selectRow->getTable()->fetchRow($selectRow);
            }

            if (!$picture) {
                return $this->_forward('notfound', 'error');
            }

            $this->view->assign(array(
                'breadcrumbs' => $breadcrumbs,
                'picture'     => array_replace(
                    $this->_helper->pic->picPageData($picture, $select, array()),
                    array(
                        'gallery2'   => true,
                        'galleryUrl' => $this->_helper->url->url(array(
                            'action' => 'category-picture-gallery'
                        ))
                    )
                )
            ));
        });
    }

    public function categoryPictureGalleryAction()
    {
        return $this->_categoryAction(function($language, $topCategory,
                $currentCategory, $categoryLang, $isOther, $topCar, $path,
                $currentCar, $breadcrumbs) {

            $pictureTable = $this->_helper->catalogue()->getPictureTable();

            $select = $pictureTable->select(true)
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->where('pictures.status IN (?)', array(
                    Picture::STATUS_NEW, Picture::STATUS_ACCEPTED
                ))
                ->order($this->_helper->catalogue()->picturesOrdering());

            if ($topCar) {
                $select
                    ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                    ->where('car_parent_cache.parent_id = ?', $currentCar->id);

            } else {

                $select
                    ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                    ->join('category_car', 'car_parent_cache.parent_id = category_car.car_id', null);

                if ($isOther) {
                    $select->where('category_car.category_id=?', $currentCategory->id);
                } else {
                    $select
                        ->join('category_parent', 'category_car.category_id = category_parent.category_id', null)
                        ->group('pictures.id')
                        ->where('category_parent.parent_id = ?', $currentCategory->id);
                }
            }

            $selectRow = clone $select;

            $pictureId = (string)$this->_getParam('picture_id');

            $selectRow
                ->where('pictures.id = ?', $pictureId)
                ->where('pictures.identity IS NULL');

            $picture = $selectRow->getTable()->fetchRow($selectRow);

            if (!$picture) {
                $selectRow = clone $select;

                $selectRow->where('pictures.identity = ?', $pictureId);

                $picture = $selectRow->getTable()->fetchRow($selectRow);
            }

            if (!$picture) {
                return $this->_forward('notfound', 'error');
            }

            return $this->_helper->json($this->_helper->pic->gallery2($select, array(
                'page'      => $this->getParam('page'),
                'pictureId' => $this->getParam('pictureId'),
                'urlParams' => array(
                    'action' => 'category-picture'
                )
            )));
        });
    }
}