<?php

use Application\Model\CarOfDay;
use Application\Model\Brand;
use Application\Model\Twins;

class IndexController extends Zend_Controller_Action
{
    private function getOrientedPictureList($car)
    {
        $perspectivesGroups = new Perspectives_Groups();

        $db = $perspectivesGroups->getAdapter();
        $perspectivesGroupIds = $db->fetchCol(
            $db->select()
                ->from($perspectivesGroups->info('name'), 'id')
                ->where('page_id = ?', 6)
                ->order('position')
        );

        $pTable = $this->_helper->catalogue()->getPictureTable();
        $pictures = array();

        $db = $pTable->getAdapter();
        $usedIds = array();

        foreach ($perspectivesGroupIds as $groupId) {
            $picture = null;

            $select = $pTable->select(true)
                ->joinRight(array('mp' => 'perspectives_groups_perspectives'), 'pictures.perspective_id=mp.perspective_id', null)
                ->where('mp.group_id = ?', $groupId)
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->where('pictures.status IN (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
                ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                ->where('car_parent_cache.parent_id = ?', $car->id)
                ->order(array(
                    'car_parent_cache.sport', 'car_parent_cache.tuning', 'mp.position',
                    'pictures.width DESC', 'pictures.height DESC'
                ))
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

        $resorted = array();
        foreach ($pictures as $picture) {
            if ($picture) {
                $resorted[] = $picture;
            }
        }
        foreach ($pictures as $picture) {
            if (!$picture) {
                $resorted[] = null;
            }
        }
        $pictures = $resorted;

        $left = array();
        foreach ($pictures as $key => $picture) {
            if (!$picture) {
                $left[] = $key;
            }
        }

        if (count($left) > 0) {
            $select = $pTable->select(true)
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                ->where('car_parent_cache.parent_id = ?', $car->id)
                ->where('pictures.status IN (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
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

    private function carLinks(Cars_Row $car)
    {
        $items = array();

        $view = $this->view;

        $db = $car->getTable()->getAdapter();
        $totalPictures = $db->fetchOne(
            $db->select()
                ->from('pictures', new Zend_Db_Expr('COUNT(1)'))
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                ->where('car_parent_cache.parent_id = ?', $car->id)
                ->where('pictures.status IN (?)', array(Picture::STATUS_NEW, Picture::STATUS_ACCEPTED))
        );

        $cateloguePaths = $this->view->car($car)->cataloguePaths();

        if ($totalPictures > 6) {

            foreach ($cateloguePaths as $path) {
                $url = $this->_helper->url->url(array(
                    'module'        => 'default',
                    'controller'    => 'catalogue',
                    'action'        => 'brand-car-pictures',
                    'brand_catname' => $path['brand_catname'],
                    'car_catname'   => $path['car_catname'],
                    'path'          => $path['path']
                ), 'catalogue', true);
                $items[] =
                    '<span class="fa fa-th"></span> ' .
                    $view->htmlA(array('href' => $url), $this->view->translate('carlist/all pictures').' ('.$totalPictures.')');
                break;
            }

            /*$brands = $car->findBrandsViaBrands_Cars();
            if (count($brands) > 0) {
                foreach ($brands as $brand) {
                    $url = $view->url(array(
                        'module'        => 'default',
                        'controller'    => 'catalogue',
                        'action'        => 'car-pictures',
                        'brand_catname' => $brand->folder,
                        'car_id'        => $car->id
                    ), 'catalogue', true);

                }
            }*/
        }

        $specService = new Application_Service_Specifications();
        if ($specService->hasSpecs(1, $car->id)) {

            foreach ($cateloguePaths as $path) {
                $items[] =
                    '<i class="fa fa-list-alt"></i> ' .
                    $view->htmlA($view->url(array(
                        'module'        => 'default',
                        'controller'    => 'catalogue',
                        'action'        => 'brand-car-specifications',
                        'brand_catname' => $path['brand_catname'],
                        'car_catname'   => $path['car_catname'],
                        'path'          => $path['path']
                    ), 'catalogue', true), $this->view->translate('carlist/specifications'));
                break;
            }
        }

        $twins = new Twins();
        foreach ($twins->getCarGroups($car->id) as $twinsGroup) {
            $items[] =
                '<i class="fa fa-adjust"></i> ' .
                $view->htmlA($view->url(array(
                    'module'         => 'default',
                    'controller'     => 'twins',
                    'action'         => 'group',
                    'twins_group_id' => $twinsGroup['id']
                ), 'twins', true), $this->view->translate('carlist/twins'));
        }

        foreach ($car->findCategoryViaCategory_Car() as $category) {
            $items[] =
                '<i class="fa fa-tag"></i> ' .
                $view->htmlA($view->url(array(
                    'controller'       => 'category',
                    'action'           => 'category',
                    'category_catname' => $category['catname'],
                ), 'category', true), $category['name']);
        }

        return $items;
    }

    private function brands()
    {
        $cache = $this->getInvokeArg('bootstrap')
            ->getResource('cachemanager')->getCache('long');

        $language = $this->_helper->language();

        $cacheKey = 'INDEX_BRANDS_HTML255' . $language;
        if (!($html = $cache->load($cacheKey))) {

            // промах кэша
            $brandModel = new Brand();

            $items = $brandModel->getTopBrandsList($language);
            foreach ($items as &$item) {
                $item['url'] = $this->_helper->url->url(array(
                    'controller'    => 'catalogue',
                    'action'        => 'brand',
                    'brand_catname' => $item['catname'],
                ), 'catalogue', true);
                $item['new_cars_url'] = $this->_helper->url->url(array(
                    'module'     => 'default',
                    'controller' => 'brands',
                    'action'     => 'newcars',
                    'brand_id'   => $item['id']
                ), 'brand_new_cars', true);
            }
            unset($item);

            $html = $this->view->partial('index/partial/brands.phtml', array(
                'brands'      => $items,
                'totalBrands' => $brandModel->getTotalCount()
            ));

            $cache->save($html, $cacheKey, array(), 1800);
        }

        return $html;
    }

    private function carOfDay()
    {
        $language = $this->_helper->language();
        $httpsFlag = $this->getRequest()->isSecure() ? '1' : '0';

        $cache = $this->getInvokeArg('bootstrap')
            ->getResource('cachemanager')->getCache('long');

        // автомобиль дня
        $model = new CarOfDay();
        $carId = $model->getCurrent();

        $carOfDayName = null;
        $carOfDayPicturesData = null;

        if ($carId) {

            $carTable = $this->_helper->catalogue()->getCarTable();
            $carOfDay = $carTable->find($carId)->current();
            if ($carOfDay) {

                $key = 'CAR_OF_DAY_77_' . $carOfDay->id . '_' . $language . '_' . $httpsFlag;

                if (!($carOfDayInfo = $cache->load($key))) {

                    $carOfDayPictures = $this->getOrientedPictureList($carOfDay);

                    // images
                    $formatRequests = array();
                    foreach ($carOfDayPictures as $idx => $picture) {
                        if ($picture) {
                            $format = $idx > 0 ? 'picture-thumb' : 'picture-thumb-medium';
                            $formatRequests[$format][$idx] = $picture->getFormatRequest();
                        }
                    }

                    $imageStorage = $this->_helper->imageStorage();

                    $imagesInfo = array();
                    foreach ($formatRequests as $format => $requests) {
                        $imagesInfo[$format] = $imageStorage->getFormatedImages($requests, $format);
                    }

                    // names
                    $notEmptyPics = array();
                    foreach ($carOfDayPictures as $idx => $picture) {
                        if ($picture) {
                            $notEmptyPics[] = $picture;
                        }
                    }
                    $pictureTable = $this->_helper->catalogue()->getPictureTable();
                    $names = $pictureTable->getNameData($notEmptyPics, array(
                        'language' => $language
                    ));

                    $carParentTable = new Car_Parent();

                    $paths = $carParentTable->getPaths($carOfDay->id, array(
                        'breakOnFirst' => true
                    ));

                    $carOfDayPicturesData = array();
                    foreach ($carOfDayPictures as $idx => $row) {
                        if ($row) {
                            $format = $idx > 0 ? 'picture-thumb' : 'picture-thumb-medium';

                            $url = null;
                            foreach ($paths as $path) {
                                $url = $this->_helper->url->url(array(
                                    'action'        => 'brand-car-picture',
                                    'brand_catname' => $path['brand_catname'],
                                    'car_catname'   => $path['car_catname'],
                                    'path'          => $path['path'],
                                    'picture_id'    => $row['identity'] ? $row['identity'] : $row['id']
                                ), 'catalogue', true);
                            }

                            $carOfDayPicturesData[] = array(
                                'src'  => isset($imagesInfo[$format][$idx]) ? $imagesInfo[$format][$idx]->getSrc() : null,
                                'name' => isset($names[$row['id']]) ? $names[$row['id']] : null,
                                'url'  => $url
                            );
                        }
                    }

                    $carOfDayName = $carOfDay->getNameData($language);

                    $carOfDayInfo = array(
                        'name'     => $carOfDayName,
                        'pictures' => $carOfDayPicturesData,
                    );

                    $cache->save($carOfDayInfo, $key, array(), 1800);
                } else {
                    $carOfDayName = $carOfDayInfo['name'];
                    $carOfDayPicturesData = $carOfDayInfo['pictures'];
                }
            }

            $carOfDayLinks = $this->carLinks($carOfDay);
        }

        return array(
            'name'     => $carOfDayName,
            'pictures' => $carOfDayPicturesData,
            'links'    => $carOfDayLinks
        );
    }

    private function factories()
    {
        $cache = $this->getInvokeArg('bootstrap')
            ->getResource('cachemanager')->getCache('long');

        $cacheKey = 'INDEX_FACTORIES_1';
        if (!($factories = $cache->load($cacheKey)))
        {

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
                $factory['url'] = $this->_helper->url->url(array(
                    'controller' => 'factory',
                    'action'     => 'factory',
                    'id'         => $factory['id']
                ));
            }
            unset($factory);

            $cache->save($factories, $cacheKey, array(), 600);
        }

        return $factories;
    }

    public function indexAction()
    {
        $brands = $this->_helper->catalogue()->getBrandTable();
        $pictures = $this->_helper->catalogue()->getPictureTable();

        $language = $this->_helper->language();

        $cache = $this->getInvokeArg('bootstrap')
            ->getResource('cachemanager')->getCache('long');

        $httpsFlag = $this->getRequest()->isSecure() ? '1' : '0';



        // группы картинок
        $select = $pictures->select(true)
            ->where('pictures.accept_datetime > DATE_SUB(CURDATE(), INTERVAL 3 DAY)')
            ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
            ->order(array('pictures.accept_datetime DESC', 'pictures.id DESC'))
            ->limit(6);

        $newPicturesData = $this->_helper->pic->listData($select, array(
            'width' => 3
        ));

        // по назначению
        $cacheKey = 'INDEX_CATEGORY7_' . $language;
        if (!($destinations = $cache->load($cacheKey)))
        {
            // промах кэша
            $categoryTable = new Category();
            $categoryAdapter = $categoryTable->getAdapter();
            $categoryLangTable = new Category_Language();

            $items = $categoryAdapter->fetchAll(
                $categoryAdapter->select()
                    ->from(
                        $categoryTable->info('name'),
                        array(
                            'id',
                            'cars_count'     => 'COUNT(1)',
                            'new_cars_count' => new Zend_Db_Expr('COUNT(IF(category_car.add_datetime > DATE_SUB(NOW(), INTERVAL 7 DAY), 1, NULL))')
                        )
                    )
                    ->join(array('cp' => 'category_parent'), 'category.id = cp.parent_id', null)
                    ->join('category_car', 'cp.category_id = category_car.category_id', null)
                    ->where('category.parent_id is null')
                    ->join('car_parent_cache', 'category_car.car_id = car_parent_cache.parent_id', null)
                    ->join('cars', 'car_parent_cache.car_id = cars.id', null)
                    ->where('not cars.is_group')
                    ->group('category.id')
                    ->order('new_cars_count DESC')
                    ->limit(15)
            );

            $destinations = array();
            foreach ($items as $item) {
                $row = $categoryTable->find($item['id'])->current();
                if (!$row) {
                    continue;
                }

                $langRow = $categoryLangTable->fetchRow(array(
                    'language = ?'    => $language,
                    'category_id = ?' => $row->id
                ));

                $destinations[] = array(
                    'url'            => $this->_helper->url->url(array(
                        'action'           => 'category',
                        'category_catname' => $row->catname,
                    ), 'category'),
                    'short_name'     => $langRow ? $langRow->short_name : $row->short_name,
                    'cars_count'     => $item['cars_count'],
                    'new_cars_count' => $item['new_cars_count']
                );
            }

            $cache->save($destinations, $cacheKey, array(), 600);
        }

        // БЛИЗНЕЦЫ
        $cacheKey = 'INDEX_INTERESTS_TWINS_BLOCK_25_' . $language;
        if (!($twinsBlock = $cache->load($cacheKey))) {
            $twins = new Twins();

            $twinsBrands = $twins->getBrands(array(
                'language' => $language,
                'limit'    => 20
            ));

            foreach ($twinsBrands as &$brand) {
                $brand['url'] = $this->_helper->url->url(array(
                    'action'        => 'brand',
                    'brand_catname' => $brand['folder']
                ), 'twins', true);
            }
            unset($brand);

            $twinsBlock = array(
                'brands'     => $twinsBrands,
                'more_count' => $twins->getTotalBrandsCount()
            );

            $cache->save($twinsBlock, $cacheKey, array(), 600);
        }

        $this->view->assign(array(
            'twinsBlock'       => $twinsBlock,
            'categories'       => $destinations,
            //'newPictures'      => $newPictures,
            'newPictures'      => $newPicturesData,
            'carOfDay'         => $this->carOfDay()
        ));

        // САМЫЕ-САМЫЕ
        $this->view->mosts = array(
            '/mosts/fastest/roadster/'          => 'mosts/fastest/roadster',
            '/mosts/mighty/sedan/today/'        => 'mosts/mighty/sedan/today',
            '/mosts/dynamic/universal/2000-09/' => 'mosts/dynamic/universal/2000-09',
            '/mosts/heavy/truck/'               => 'mosts/heavy/truck'
        );

        $userTable = new Users();

        $cacheKey = 'INDEX_SPEC_CARS_5_' . $language;
        if (!($cars = $cache->load($cacheKey))) {

            $carTable = $this->_helper->catalogue()->getCarTable();

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

            $cache->save($cars, $cacheKey, array(), 300);
        }

        $specService = new Application_Service_Specifications();

        $specsCars = $this->_helper->car->listData($cars, array(
            'disableLargePictures' => true,
            'perspectiveGroup'     => 1,
            'allowUpPictures'      => true,
            'disableDescription'   => true,
            'callback'             => function(&$item) use ($userTable, $specService) {
                $contribPairs = $specService->getContributors(1, array($item['id']));
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
            'pictureUrl' => function($listCar, $picture) {
                return $this->_helper->pic->href($picture);
            }
        ));

        $this->view->specsCars = $specsCars;


        $cacheKey = 'INDEX_PAGE_DATA_6_' . $language;
        if (!($pageData = $cache->load($cacheKey))) {

            $pageData = [
                'statistics' => [
                    'name' => $this->view->page(173)->name,
                    'url'  => '/users/rating'
                ],
                'mosts' => [
                    'name' => $this->view->page(21)->name,
                    'url'  => '/mosts/'
                ],
                'category' => [
                    'name' => $this->view->page(22)->name,
                    'url'  => '/category/'
                ],
                'twins' => [
                    'name' => $this->view->page(25)->name,
                    'url'  => '/twins/'
                ],
                'new' => [
                    'name' => $this->view->page(51)->name,
                    'url'  => '/new'
                ],
            ];

            $cache->save($pageData, $cacheKey, array(), 300);
        }

        $this->view->assign(array(
            'pageData'   => $pageData,
            'brandsHtml' => $this->brands(),
            'factories'  => $this->factories()
        ));
    }
}