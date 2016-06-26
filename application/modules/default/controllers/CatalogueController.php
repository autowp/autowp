<?php

use Application\Service\Mosts;
use Application\Model\DbTable\BrandLink;
use Application\Model\Brand;
use Application\Model\DbTable\Modification as ModificationTable;

class CatalogueController extends Zend_Controller_Action
{
    private $_mostsMinCarsCount = 200;

    private function _brandAction(Callable $callback)
    {
        $language = $this->_helper->language();

        $brandModel = new Brand();

        $brand = $brandModel->getBrandByCatname($this->getParam('brand_catname'), $language);

        if (!$brand) {
            return $this->forward('notfound', 'error');
        }

        $this->view->assign(array(
            'brand' => $brand
        ));

        $callback($brand);
    }

    /**
     * @param Zend_Db_Table_Select $select
     * @param string $pictureId
     * @return Picture_Row
     */
    private function fetchSelectPicture(Zend_Db_Table_Select $select, $pictureId)
    {
        $selectRow = clone $select;

        $selectRow
            ->where('pictures.id = ?', (int)$pictureId)
            ->where('pictures.identity IS NULL');

        $picture = $selectRow->getTable()->fetchRow($selectRow);

        if (!$picture) {
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
            ->order($this->_helper->catalogue()->picturesOrdering());
    }

    /**
     *
     * @return Zend_Db_Table_Select
     */
    private function selectFromPictures($onlyAccepted = true)
    {
        $select = $this->_helper->catalogue()->getPictureTable()->select(true);

        if ($onlyAccepted) {
            $select->where('pictures.status IN (?)', array(
                Picture::STATUS_NEW, Picture::STATUS_ACCEPTED
            ));
        }

        return $select;
    }

    /**
     * @param Zend_Db_Table_Select $select
     * @param int $page
     * @return Zend_Paginator
     */
    private function carsPaginator(Zend_Db_Table_Select $select, $page)
    {
        return Zend_Paginator::factory($select)
            ->setItemCountPerPage($this->_helper->catalogue()->getCarsPerPage())
            ->setCurrentPageNumber($page);
    }

    private function carsOrder()
    {
        return $this->_helper->catalogue()->carsOrdering();
    }

    private function picturesPaginator(Zend_Db_Table_Select $select, $page)
    {
        return Zend_Paginator::factory($select)
            ->setItemCountPerPage($this->_helper->catalogue()->getPicturesPerPage())
            ->setCurrentPageNumber($page);
    }

    private function getCarShortName($brand, $carName)
    {
        $shortCaption = $carName;
        $patterns = array(
            preg_quote($brand['name'].'-', '|') => '',
            preg_quote($brand['name'], '|') => '',
            '[[:space:]]+' => ' '
        );
        foreach ($patterns as $pattern => $replacement) {
            $shortCaption = preg_replace('|'.$pattern.'|isu', $replacement, $shortCaption);
        }

        $shortCaption = trim($shortCaption);

        return $shortCaption;
    }

    public function recentAction()
    {
        $this->_brandAction(function($brand) {

            $select = $this->selectFromPictures()
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand['id'])
                /*->join('brands_pictures_cache', 'pictures.id=brands_pictures_cache.picture_id', null)
                ->where('brands_pictures_cache.brand_id = ?', $brand['id'])*/
                ->group('pictures.id')
                ->order(array(
                    'pictures.accept_datetime DESC',
                    'pictures.add_date DESC',
                    'pictures.id DESC'
                ));

            $paginator = $this->picturesPaginator($select, $this->getParam('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->forward('notfound', 'error');
            }

            $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

            $picturesData = $this->_helper->pic->listData($select, array(
                'width' => 4
            ));

            $this->view->assign(array(
                'paginator'    => $paginator,
                'picturesData' => $picturesData,
            ));

            $this->_helper->actionStack('brand', 'sidebar', 'default', array(
                'brand_id' => $brand['id'],
            ));
        });
    }

    public function conceptsAction()
    {
        $this->_brandAction(function($brand) {

            $select = $this->_helper->catalogue()->getCarTable()->select(true)
                ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand['id'])
                ->where('cars.is_concept')
                ->where('not cars.is_concept_inherit')
                ->group('cars.id')
                ->order($this->carsOrder());

            $paginator = $this->carsPaginator($select, $this->getParam('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->forward('notfound', 'error');
            }

            $carParentTable = new Car_Parent();

            $specService = new Application_Service_Specifications();

            $this->view->assign(array(
                'paginator' => $paginator,
                'listData'  => $this->_helper->car->listData($paginator->getCurrentItems(), array(
                    'detailsUrl' => function($listCar) use ($brand, $carParentTable) {

                        $paths = $carParentTable->getPathsToBrand($listCar->id, $brand['id'], array(
                            'breakOnFirst' => true
                        ));

                        if (count($paths) <= 0) {
                            return false;
                        }

                        $path = $paths[0];

                        return $this->_helper->url->url(array(
                            'module'        => 'default',
                            'controller'    => 'catalogue',
                            'action'        => 'brand-car',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path']
                        ), 'catalogue', true);
                    },
                    'allPicturesUrl' => function($listCar) use ($brand, $carParentTable) {

                        $paths = $carParentTable->getPathsToBrand($listCar->id, $brand['id'], array(
                            'breakOnFirst' => true
                        ));

                        if (count($paths) <= 0) {
                            return false;
                        }

                        $path = $paths[0];

                        return $this->_helper->url->url(array(
                            'module'        => 'default',
                            'controller'    => 'catalogue',
                            'action'        => 'brand-car-pictures',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path'],
                            'exact'         => false
                        ), 'catalogue', true);
                    },
                    'specificationsUrl' => function($listCar) use ($brand, $carParentTable, $specService) {

                        $hasSpecs = $specService->hasSpecs(1, $listCar->id);

                        if (!$hasSpecs) {
                            return false;
                        }

                        $paths = $carParentTable->getPathsToBrand($listCar->id, $brand['id'], array(
                            'breakOnFirst' => true
                        ));

                        if (count($paths) <= 0) {
                            return false;
                        }

                        $path = $paths[0];

                        return $this->_helper->url->url(array(
                            'module'        => 'default',
                            'controller'    => 'catalogue',
                            'action'        => 'brand-car-specifications',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path'],
                        ), 'catalogue', true);
                    },
                    'pictureUrl' => function($listCar, $picture) use ($brand, $carParentTable) {

                        $paths = $carParentTable->getPathsToBrand($listCar->id, $brand['id'], array(
                            'breakOnFirst' => true
                        ));

                        if (count($paths) <= 0) {
                            return $this->_helper->pic->url($picture['id'], $picture['identity']);
                        }

                        $path = $paths[0];

                        return $this->_helper->url->url(array(
                            'module'        => 'default',
                            'controller'    => 'catalogue',
                            'action'        => 'brand-car-picture',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path'],
                            'picture_id'    => $picture['identity'] ? $picture['identity'] : $picture['id']
                        ), 'catalogue', true);
                    }
                ))
            ));

            $this->_helper->actionStack('brand', 'sidebar', 'default', array(
                'brand_id'    => $brand['id'],
                'is_concepts' => true
            ));
        });
    }

    public function carsAction()
    {
        $this->_brandAction(function($brand) {

            $carTypeTable = new Car_Types();

            $cartype = false;
            if ($this->getParam('cartype_catname')) {
                $cartype = $carTypeTable->fetchRow(
                    $carTypeTable->select()
                        ->from($carTypeTable, array('id', 'name', 'catname'))
                        ->where('catname = ?', $this->getParam('cartype_catname'))
                );

                if (!$cartype) {
                    return $this->forward('notfound', 'error');
                }
            }

            $carTypeAdapter = $carTypeTable->getAdapter();
            $select = $carTypeAdapter->select()
                ->from($carTypeTable->info('name'), array(
                    'id',
                    'cars_count' => new Zend_db_Expr('COUNT(1)')
                ))
                ->join('cars', 'car_types.id = cars.car_type_id', null)
                ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand['id'])
                ->where('cars.begin_year or cars.begin_model_year')
                ->where('not cars.is_group')
                ->group('car_types.id')
                ->order('car_types.position');

            $list = array();
            foreach ($carTypeAdapter->fetchAll($select) as $row) {
                $carType = $carTypeTable->find($row['id'])->current();
                if ($carType) {
                    $list[] = array(
                        'id'        => $carType->id,
                        'name'      => $carType->name,
                        'carsCount' => $row['cars_count'],
                        'url'       => $this->_helper->url->url(array(
                            'cartype_catname' => $carType->catname,
                            'page'            => 1
                        ), 'catalogue')
                    );
                }
            }

            $select = $this->_helper->catalogue()->getCarTable()->select(true)
                ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand['id'])
                ->where('cars.begin_year or cars.begin_model_year')
                ->where('not cars.is_group')
                ->group('cars.id')
                ->order($this->carsOrder());
            if ($cartype) {
                $select->where('cars.car_type_id = ?', $cartype->id);
            }

            $paginator = $this->carsPaginator($select, $this->getParam('page'));

            if (!$paginator->getTotalItemCount()) {
                return $this->forward('notfound', 'error');
            }

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->forward('notfound', 'error');
            }

            $carParentTable = new Car_Parent();

            $specService = new Application_Service_Specifications();

            $this->view->assign(array(
                'cartypes'  => $list,
                'cartype'   => $cartype,
                'paginator' => $paginator,
                'listData'  => $this->_helper->car->listData($paginator->getCurrentItems(), array(
                    'detailsUrl' => function($listCar) use ($brand, $carParentTable) {

                        $paths = $carParentTable->getPathsToBrand($listCar->id, $brand['id'], array(
                            'breakOnFirst' => true
                        ));

                        if (count($paths) <= 0) {
                            return false;
                        }

                        $path = $paths[0];

                        return $this->_helper->url->url(array(
                            'module'        => 'default',
                            'controller'    => 'catalogue',
                            'action'        => 'brand-car',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path']
                        ), 'catalogue', true);
                    },
                    'allPicturesUrl' => function($listCar) use ($brand, $carParentTable) {

                        $paths = $carParentTable->getPathsToBrand($listCar->id, $brand['id'], array(
                            'breakOnFirst' => true
                        ));

                        if (count($paths) <= 0) {
                            return false;
                        }

                        $path = $paths[0];

                        return $this->_helper->url->url(array(
                            'module'        => 'default',
                            'controller'    => 'catalogue',
                            'action'        => 'brand-car-pictures',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path'],
                            'exact'         => false
                        ), 'catalogue', true);
                    },
                    'specificationsUrl' => function($listCar) use ($brand, $specService, $carParentTable) {

                        $hasSpecs = $specService->hasSpecs(1, $listCar->id);

                        if (!$hasSpecs) {
                            return false;
                        }

                        $paths = $carParentTable->getPathsToBrand($listCar->id, $brand['id'], array(
                            'breakOnFirst' => true
                        ));

                        if (count($paths) <= 0) {
                            return false;
                        }

                        $path = $paths[0];

                        return $this->_helper->url->url(array(
                            'module'        => 'default',
                            'controller'    => 'catalogue',
                            'action'        => 'brand-car-specifications',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path'],
                        ), 'catalogue', true);
                    },
                    'pictureUrl' => function($listCar, $picture) use ($brand, $carParentTable) {

                        $paths = $carParentTable->getPathsToBrand($listCar->id, $brand['id'], array(
                            'breakOnFirst' => true
                        ));

                        if (count($paths) <= 0) {
                            return $this->_helper->pic->url($picture['id'], $picture['identity']);
                        }

                        $path = $paths[0];

                        return $this->_helper->url->url(array(
                            'module'        => 'default',
                            'controller'    => 'catalogue',
                            'action'        => 'brand-car-picture',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path'],
                            'picture_id'    => $picture['identity'] ? $picture['identity'] : $picture['id']
                        ), 'catalogue', true);
                    }
                ))
            ));

            $this->_helper->actionStack('brand', 'sidebar', 'default', array(
                'brand_id' => $brand['id']
            ));
        });
    }

    private function getBrandFactories($brandId)
    {
        $factoryTable = new Factory();
        $db = $factoryTable->getAdapter();
        $rows = $db->fetchAll(
            $db->select()
                ->from('factory', ['factory_id' => 'id', 'factory_name' => 'name', 'cars_count' => 'count(car_parent_cache.car_id)'])
                ->join('factory_car', 'factory.id = factory_car.factory_id', null)
                ->join('car_parent_cache', 'factory_car.car_id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brandId)
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
        $requests = array();
        foreach ($rows as $idx => $picture) {
            $requests[$idx] = Pictures_Row::buildFormatRequest($picture);
        }

        $imagesInfo = $this->_helper->imageStorage()->getFormatedImages($requests, 'picture-thumb');

        $factories = [];
        foreach ($rows as $idx => $row) {

            $id = (int)$row['id'];

            $name = isset($names[$id]) ? $names[$id] : null;

            $factories[] = [
                'name' => $row['factory_name'],
                'url'  => $this->_helper->url->url([
                    'controller' => 'factory',
                    'action'     => 'factory',
                    'id'         => $row['factory_id']
                ], 'default', true),
                'src'       => isset($imagesInfo[$idx]) ? $imagesInfo[$idx]->getSrc() : null
            ];
        }

        return $factories;
    }

    public function brandAction()
    {
        $this->_brandAction(function($brand) {

            $language = $this->_helper->language();

            $cache = $this->getInvokeArg('bootstrap')
                ->getResource('cachemanager')->getCache('long');

            $pictures = $this->_helper->catalogue()->getPictureTable();
            $key = 'BRAND_'.$brand['id'].'_TOP_PICTURES_5_' . $language;
            if (!($topPictures = $cache->load($key))) {

                $select = $this->selectOrderFromPictures()
                    ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                    ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                    ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                    ->where('brands_cars.brand_id = ?', $brand['id'])
                    ->where('pictures.status IN (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
                    ->group('pictures.id')
                    ->limit(12);

                $carParentTable = new Car_Parent();

                $topPictures = $this->_helper->pic->listData($select, array(
                    'width' => 4,
                    'url'   => function($picture) use ($carParentTable, $brand) {

                        if (!$picture['car_id']) {
                            return $this->_helper->pic->url($picture['id'], $picture['identity']);
                        }

                        $paths = $carParentTable->getPathsToBrand($picture['car_id'], $brand['id'], array(
                            'breakOnFirst' => true
                        ));

                        if (count($paths) <= 0) {
                            return $this->_helper->pic->url($picture['id'], $picture['identity']);
                        }

                        $path = $paths[0];

                        return $this->_helper->url->url(array(
                            'module'        => 'default',
                            'controller'    => 'catalogue',
                            'action'        => 'brand-car-picture',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path'],
                            'picture_id'    => $picture['identity'] ? $picture['identity'] : $picture['id']
                        ), 'catalogue', true);
                    }
                ));

                $cache->save($topPictures, $key, array(), 60 * 10);
            }

            $types = array(
                'official' => array(),
                'helper'   => array(),
                'club'     => array(),
                'default'  => array()
            );

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

            $cars = $this->_helper->catalogue()->getCarTable();

            $haveTwins = $cars->getAdapter()->fetchOne(
                $cars->getAdapter()->select()
                    ->from($cars->info('name'), 'id')
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                    ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                    ->join('twins_groups_cars', 'cars.id = twins_groups_cars.car_id', null)
                    ->where('brands_cars.brand_id = ?', $brand['id'])
                    ->limit(1)
            );

            $description = null;
            if ($brand['text_id']) {
                $textStorage = $this->_helper->textStorage();
                $description = $textStorage->getText($brand['text_id']);
            }



            $this->view->assign(array(
                'topPictures' => $topPictures,
                'link_types'  => $types,
                'haveTwins'   => $haveTwins,
                'mostsActive' => $this->mostsActive($brand['id']),
                'description' => $description,
                'factories'   => $this->getBrandFactories($brand['id'])
            ));

            $this->_helper->actionStack('brand', 'sidebar', 'default', array(
                'brand_id' => $brand['id']
            ));
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
        $this->_brandAction(function($brand) use ($type) {

            $select = $this->typePicturesSelect($brand['id'], $type);

            $paginator = $this->picturesPaginator($select, $this->getParam('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->forward('notfound', 'error');
            }

            $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

            $picturesData = $this->_helper->pic->listData($select, array(
                'width' => 4,
                'url'   => function($row) {
                    return $this->_helper->url->url(array(
                        'action'     => $this->getParam('action') . '-picture',
                        'picture_id' => $row['identity'] ? $row['identity'] : $row['id']
                    ));
                }
            ));

            $this->view->assign(array(
                'brand'        => $brand,
                'paginator'    => $paginator,
                'picturesData' => $picturesData
            ));

            $this->_helper->actionStack('brand', 'sidebar', 'default', array(
                'brand_id' => $brand['id'],
                'type'     => $type
            ));

        });
    }

    public function otherAction()
    {
        $this->typePictures(Picture::UNSORTED_TYPE_ID);
    }

    public function mixedAction()
    {
        $this->typePictures(Picture::MIXED_TYPE_ID);
    }

    public function logotypesAction()
    {
        $this->typePictures(Picture::LOGO_TYPE_ID);
    }

    private function typePicturesPicture($type)
    {
        $this->_brandAction(function($brand) use ($type) {

            $select = $this->typePicturesSelect($brand['id'], $type, false);

            $this->_pictureAction($select, function($select, $picture) use ($brand, $type) {

                $this->view->assign(array(
                    'picture'     => array_replace(
                        $this->_helper->pic->picPageData($picture, $select),
                        [
                            'gallery2'   => true,
                            'galleryUrl' => $this->_helper->url->url([
                                'action'  => str_replace('-picture', '-gallery', $this->getParam('action')),
                                'gallery' => $this->galleryType($picture)
                            ])
                        ]
                    )
                ));

                $this->_helper->actionStack('brand', 'sidebar', 'default', array(
                    'brand_id' => $brand['id'],
                    'type'     => $type
                ));

            });

        });
    }

    public function otherPictureAction()
    {
        $this->typePicturesPicture(Picture::UNSORTED_TYPE_ID);
    }

    public function mixedPictureAction()
    {
        $this->typePicturesPicture(Picture::MIXED_TYPE_ID);
    }

    public function logotypesPictureAction()
    {
        $this->typePicturesPicture(Picture::LOGO_TYPE_ID);
    }

    private function typePicturesGallery($type)
    {
        $this->_brandAction(function($brand) use ($type) {

            $select = $this->typePicturesSelect($brand['id'], $type, false);

            switch ($this->getParam('gallery')) {
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

            return $this->_helper->json($this->_helper->pic->gallery2($select, array(
                'page'      => $this->getParam('page'),
                'pictureId' => $this->getParam('pictureId'),
                'urlParams' => array(
                    'action' => str_replace('-gallery', '-picture', $this->getParam('action'))
                )
            )));

        });
    }

    public function otherGalleryAction()
    {
        $this->typePicturesGallery(Picture::UNSORTED_TYPE_ID);
    }

    public function mixedGalleryAction()
    {
        $this->typePicturesGallery(Picture::MIXED_TYPE_ID);
    }

    public function logotypesGalleryAction()
    {
        $this->typePicturesGallery(Picture::LOGO_TYPE_ID);
    }

    private function _enginesAction($callback)
    {
        $this->_brandAction(function($brand) use($callback) {

            $engineTable = new Engines();

            $path = $this->getParam('path');
            $path = $path ? (array)$path : array();

            $prevEngine = null;
            foreach ($path as $node) {
                $filter = array(
                    'id = ?' => (int)$node,
                );
                if (!$prevEngine) {

                    $currentEngine = $engineTable->fetchRow(
                        $engineTable->select(true)
                            ->where('engines.id = ?', (int)$node)
                            ->join('brand_engine', 'engines.id = brand_engine.engine_id', null)
                            ->where('brand_engine.brand_id = ?', $brand['id'])
                    );
                } else {
                    $currentEngine = $engineTable->fetchRow(array(
                        'id = ?'        => (int)$node,
                        'parent_id = ?' => $prevEngine->id
                    ));
                }

                if (!$currentEngine) {
                    return $this->forward('notfound', 'error');
                }
                $prevEngine = $currentEngine;
            }

            $engineRow = $prevEngine;

            return $callback($brand, $engineRow, $path);

        });
    }

    public function enginesAction()
    {
        $this->_enginesAction(function($brand, $engineRow, $path) {

            $engineTable = new Engines();

            $select = $engineTable->select(true)
                ->order('engines.caption');

            if ($engineRow) {
                $select
                    ->where('engines.parent_id = ?', $engineRow->id);
            } else {
                $select
                    ->join('brand_engine', 'engines.id = brand_engine.engine_id', null)
                    ->where('brand_engine.brand_id = ?', $brand['id']);
            }

            $paginator = Zend_Paginator::factory($select)
                ->setItemCountPerPage(20)
                ->setCurrentPageNumber($this->getParam('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->forward('engine-cars');
            }

            $pictureTable = $this->_helper->catalogue()->getPictureTable();
            $specService = new Application_Service_Specifications();
            $carTable = $this->_helper->catalogue()->getCarTable();

            $language = $this->_helper->language();

            $isModer = $this->_helper->user()->inheritsRole('moder');
            $logedIn = $this->_helper->user()->logedIn();

            $picturesLimit = 4;

            $engines = array();
            foreach ($paginator->getCurrentItems() as $engine) {
                $pictureRows = $pictureTable->fetchAll(
                    $this->selectFromPictures()
                        ->join('engine_parent_cache', 'pictures.engine_id = engine_parent_cache.engine_id', null)
                        ->where('engine_parent_cache.parent_id = ?', $engine->id)
                        ->where('pictures.type = ?', Picture::ENGINE_TYPE_ID)
                        ->order('pictures.id')
                        ->limit(4)
                );

                $pictures = array();
                foreach ($pictureRows as $pictureRow) {
                    //$pictures[] = $pictureRow;

                    $caption = $pictureRow->getCaption(array(
                        'language' => $language
                    ));

                    $url = $this->_helper->url->url(array(
                        'action'     => 'engine-picture',
                        'path'       => array_merge($path, [$engine->id]),
                        'picture_id' => $pictureRow['identity'] ? $pictureRow['identity'] : $pictureRow['id']
                    ));

                    $pictures[] = array(
                        'name' => $caption,
                        'url'  => $url,
                        'img'  => $pictureRow->getFormatRequest()
                    );
                }

                $morePictures = $picturesLimit - count($pictures);
                if ($morePictures > 0) {
                    $pictureRows = $pictureTable->fetchAll(
                        $this->selectFromPictures()
                            ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                            ->where('pictures.perspective_id = ?', 17) // under the hood
                            ->join('cars', 'pictures.car_id = cars.id', null)
                            ->join('engine_parent_cache', 'cars.engine_id = engine_parent_cache.engine_id', null)
                            ->where('engine_parent_cache.parent_id = ?', $engine->id)
                            ->order('pictures.id')
                            ->limit($morePictures)
                    );
                    foreach ($pictureRows as $pictureRow) {
                        //$pictures[] = $pictureRow;
                        $caption = $pictureRow->getCaption(array(
                            'language' => $language
                        ));

                        $url = $this->_helper->pic->href($pictureRow->toArray());

                        $pictures[] = array(
                            'name' => $caption,
                            'url'  => $url,
                            'img'  => $pictureRow->getFormatRequest()
                        );
                    }
                }


                $moderUrl = null;
                $specsUrl = null;
                $specsEditUrl = null;
                $detailsUrl = null;

                if ($isModer) {
                    $moderUrl = $this->_helper->url->url(array(
                        'module'     => 'moder',
                        'controller' => 'engines',
                        'action'     => 'engine',
                        'engine_id'  => $engine->id
                    ), 'default', true);
                }

                if ($logedIn) {
                    $specsEditUrl = $this->_helper->url->url(array(
                        'module'     => 'default',
                        'controller' => 'cars',
                        'action'     => 'engine-spec-editor',
                        'engine_id'  => $engine->id
                    ), 'default', true);
                }

                $hasSpecs = $specService->hasSpecs(3, $engine->id);

                if ($hasSpecs) {
                    $specsUrl = $this->_helper->url->url(array(
                        'module'        => 'default',
                        'controller'    => 'catalogue',
                        'action'        => 'engine-specs',
                        'brand_catname' => $brand['catname'],
                        'path'          => array_merge($path, array($engine->id))
                    ), 'catalogue', true);
                }

                $childsCount = $engineTable->getAdapter()->fetchOne(
                    $engineTable->getAdapter()->select()
                        ->from($engineTable->info('name'), new Zend_Db_Expr('count(1)'))
                        ->where('parent_id = ?', $engine->id)
                );

                if ($childsCount) {
                    $detailsUrl = $this->_helper->url->url(array(
                        'module'        => 'default',
                        'controller'    => 'catalogue',
                        'action'        => 'engines',
                        'brand_catname' => $brand['catname'],
                        'path'          => array_merge($path, array($engine->id))
                    ), 'catalogue', true);
                }

                $carIds = $engine->getRelatedCarGroupId([
                    'groupJoinLimit' => 3
                ]);
                $cars = array();
                if ($carIds) {
                    $carRows = $carTable->fetchAll(array(
                        'id in (?)' => $carIds
                    ), $this->_helper->catalogue()->carsOrdering());

                    foreach ($carRows as $carRow) {
                        $cataloguePaths = $this->_helper->catalogue()->cataloguePaths($carRow);

                        foreach ($cataloguePaths as $cPath) {
                            $cars[] = array(
                                'name' => $carRow->getNameData($language),
                                'url'  => $this->_helper->url->url(array(
                                    'module'        => 'default',
                                    'controller'    => 'catalogue',
                                    'action'        => 'brand-car',
                                    'brand_catname' => $cPath['brand_catname'],
                                    'car_catname'   => $cPath['car_catname'],
                                    'path'          => $cPath['path']
                                ), 'catalogue', true)
                            );
                            break;
                        }
                    }
                }

                $engines[] = array(
                    'name'         => $engine->caption,
                    'pictures'     => $pictures,
                    'moderUrl'     => $moderUrl,
                    'specsUrl'     => $specsUrl,
                    'specsEditUrl' => $specsEditUrl,
                    'detailsUrl'   => $detailsUrl,
                    'childsCount'  => $childsCount,
                    'cars'         => $cars
                );
            }

            $carsCount = null;
            $childsCount = null;
            $specsCount = null;
            $picturesCount = null;

            if ($engineRow) {
                $select = $carTable->select(true)
                    ->join('engine_parent_cache', 'cars.engine_id = engine_parent_cache.engine_id', null)
                    ->where('engine_parent_cache.parent_id = ?', $engineRow->id);

                $carsCount = Zend_Paginator::factory($select)->getTotalItemCount();

                $select = $engineTable->select(true)
                    ->where('engines.parent_id = ?', $engineRow->id);

                $childsCount = Zend_Paginator::factory($select)->getTotalItemCount();

                $specsCount = $specService->getSpecsCount(3, $engineRow->id);

                $picturesSelect = $this->_enginePicturesSelect($engineRow, true);
                $picturesCount = Zend_Paginator::factory($picturesSelect)->getTotalItemCount();
            }

            $this->view->assign(array(
                'engine'      => $engineRow,
                'brand'       => $brand,
                'paginator'   => $paginator,
                'engines'     => $engines,
                'carsCount'   => $carsCount,
                'childsCount' => $childsCount,
                'specsCount'  => $specsCount,
                'picturesCount' => $picturesCount
            ));

            $this->_helper->actionStack('brand', 'sidebar', 'default', array(
                'brand_id'   => $brand['id'],
                'is_engines' => true
            ));
        });
    }

    public function engineSpecsAction()
    {
        $this->_engineAction(function($brand, $engineRow, $path) {

            $engine = array(
                'id'   => $engineRow->id,
                'name' => $engineRow->caption
            );

            $specService = new Application_Service_Specifications();

            $specs = $specService->engineSpecifications(array($engine), array(
                'language' => 'en'
            ));

            $this->view->assign(array(
                'engine'      => $engine,
                'specs'       => $specs,
            ));

            $this->_helper->actionStack('brand', 'sidebar', 'default', array(
                'brand_id'   => $brand['id'],
                'is_engines' => true
            ));

        });
    }

    private function _engineAction(Callable $callback)
    {
        $this->_enginesAction(function($brand, $engineRow, $path) use ($callback) {
            if (!$engineRow) {
                return $this->forward('notfound', 'error');
            }

            $carTable = new Cars();
            $select = $carTable->select(true)
                ->join('engine_parent_cache', 'cars.engine_id = engine_parent_cache.engine_id', null)
                ->where('engine_parent_cache.parent_id = ?', $engineRow->id);

            $carsCount = Zend_Paginator::factory($select)->getTotalItemCount();

            $engineTable = new Engines();
            $select = $engineTable->select(true)
                ->where('engines.parent_id = ?', $engineRow->id);

            $childsCount = Zend_Paginator::factory($select)->getTotalItemCount();

            $specService = new Application_Service_Specifications();
            $specsCount = $specService->getSpecsCount(3, $engineRow->id);

            $picturesSelect = $this->_enginePicturesSelect($engineRow, true);
            $picturesCount = Zend_Paginator::factory($picturesSelect)->getTotalItemCount();

            $this->view->assign(array(
                'brand'        => $brand,
                'engine'       => array(
                    'id'   => $engineRow->id,
                    'name' => $engineRow->caption
                ),
                'carsCount'    => $carsCount,
                'childsCount'  => $childsCount,
                'specsCount'   => $specsCount,
                'picturesCount' => $picturesCount
            ));

            $callback($brand, $engineRow, $path);
        });
    }

    private function _enginePicturesSelect($engine, $onlyAccepted = true)
    {
        return $this->selectOrderFromPictures($onlyAccepted)
            ->where('pictures.type = ?', Picture::ENGINE_TYPE_ID)
            ->join('engine_parent_cache', 'pictures.engine_id = engine_parent_cache.engine_id', null)
            ->where('engine_parent_cache.parent_id = ?', $engine->id);
    }

    public function engineGalleryAction()
    {
        $this->_engineAction(function($brand, $engineRow, $path) {
            $select = $this->_enginePicturesSelect($engineRow, false);

            switch ($this->getParam('gallery')) {
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

            return $this->_helper->json($this->_helper->pic->gallery2($select, array(
                'page'      => $this->getParam('page'),
                'pictureId' => $this->getParam('pictureId'),
                'urlParams' => array(
                    'action' => 'engine-picture'
                )
            )));
        });
    }

    public function enginePictureAction()
    {
        $this->_engineAction(function($brand, $engineRow, $path) {
            $select = $this->_enginePicturesSelect($engineRow, false);

            $this->_pictureAction($select, function($select, $picture) use ($brand, $engineRow) {

                $this->view->assign(array(
                    'picture'     => array_replace(
                        $this->_helper->pic->picPageData($picture, $select),
                        array(
                            'gallery2'   => true,
                            'galleryUrl' => $this->_helper->url->url(array(
                                'action'  => 'engine-gallery',
                                'gallery' => $this->galleryType($picture)
                            ))
                        )
                    )
                ));

                $this->_helper->actionStack('brand', 'sidebar', 'default', array(
                    'brand_id'   => $brand['id'],
                    'is_engines' => true
                ));

            });
        });
    }

    public function enginePicturesAction()
    {
        $this->_engineAction(function($brand, $engineRow, $path) {
            $select = $this->_enginePicturesSelect($engineRow);

            $paginator = $this->picturesPaginator($select, $this->getParam('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->forward('notfound', 'error');
            }

            $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

            $picturesData = $this->_helper->pic->listData($select, array(
                'width' => 4,
                'url'   => function($row) {
                    return $this->_helper->url->url(array(
                        'action'     => 'engine-picture',
                        'picture_id' => $row['identity'] ? $row['identity'] : $row['id']
                    ));
                }
            ));

            $this->view->assign(array(
                'paginator'    => $paginator,
                'picturesData' => $picturesData
            ));

            $this->_helper->actionStack('brand', 'sidebar', 'default', array(
                'brand_id'   => $brand['id'],
                'is_engines' => true
            ));
        });
    }

    public function engineCarsAction()
    {
        $this->_engineAction(function($brand, $engineRow, $path) {

            $engine = array(
                'id'   => $engineRow->id,
                'name' => $engineRow->caption
            );

            $carIds = $engineRow->getRelatedCarGroupId();
            $carRows = array();
            if ($carIds) {
                $carTable = $this->_helper->catalogue()->getCarTable();

                $carRows = $carTable->fetchAll(array(
                    'id in (?)' => $carIds
                ), $this->_helper->catalogue()->carsOrdering());
            }

            $carParentTable = new Car_Parent();

            $this->view->assign(array(
                'cars'        => $this->_helper->car->listData($carRows, array(
                    'pictureUrl' => function($listCar, $picture) use ($brand, $carParentTable) {

                        $paths = $carParentTable->getPaths($listCar->id, array(
                            'breakOnFirst' => true
                        ));

                        if (count($paths) <= 0) {
                            return $this->_helper->pic->url($picture['id'], $picture['identity']);
                        }

                        $path = $paths[0];

                        return $this->_helper->url->url(array(
                            'module'        => 'default',
                            'controller'    => 'catalogue',
                            'action'        => 'brand-car-picture',
                            'brand_catname' => $path['brand_catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path'],
                            'picture_id'    => $picture['identity'] ? $picture['identity'] : $picture['id']
                        ), 'catalogue', true);
                    }
                )),
            ));

            $this->_helper->actionStack('brand', 'sidebar', 'default', array(
                'brand_id'   => $brand['id'],
                'is_engines' => true
            ));

        });
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
        $result = array();

        if (count($ids)) {
            $carTable = $this->_helper->catalogue()->getCarTable();
            $db = $carTable->getAdapter();

            $language = $this->_helper->language();

            $rows = $db->fetchAll(
                $db->select()
                    ->from('cars', array(
                        'cars.id',
                        'name' => 'if(car_language.name, car_language.name, cars.caption)',
                        'cars.begin_model_year', 'cars.end_model_year',
                        'spec' => 'spec.short_name',
                        'spec_full' => 'spec.name',
                        'cars.body', 'cars.today',
                        'cars.begin_year', 'cars.end_year'
                    ))
                    ->joinLeft('car_language', 'cars.id = car_language.car_id and car_language.language = :lang', null)
                    ->joinLeft('spec', 'cars.spec_id = spec.id', null)
                    ->where('cars.id in (?)', $ids),
                array(
                    'lang' => $language
                )
            );

            foreach ($rows as $row) {
                $result[$row['id']] = Cars_Row::buildFullName($row);
            }
        }

        return $result;
    }

    private function _brandCarAction(Callable $callback)
    {
        $this->_brandAction(function($brand) use ($callback) {

            $carTable = $this->_helper->catalogue()->getCarTable();

            $language = $this->_helper->language();

            $path = $this->getParam('path');
            $path = $path ? (array)$path : array();
            $path = array_values($path);

            $db = $carTable->getAdapter();
            $select = $db->select()
                ->from('cars', array())
                ->joinLeft('car_language', 'cars.id = car_language.car_id and car_language.language = :lang', null)
                ->joinLeft('spec', 'cars.spec_id = spec.id', null);

            $columns = array(
                'cars.id',
                'cars.is_concept',
                'name' => 'if(car_language.name, car_language.name, cars.caption)',
                'cars.begin_model_year', 'cars.end_model_year',
                'spec' => 'spec.short_name',
                'cars.body', 'cars.today', 'cars.produced', 'cars.produced_exactly',
                'cars.begin_year', 'cars.end_year', 'cars.begin_month', 'cars.end_month',
                'cars.is_group', 'cars.full_text_id', 'cars.text_id',
                'brand_car_catname' => 'brands_cars.catname'
            );

            $field = 'cars.id';
            foreach (array_reverse($path) as $idx => $pathNode) {
                $cpAlias = 'cp'. $idx;
                $select
                    ->join(
                        array($cpAlias => 'car_parent'),
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
                ->join('brands_cars', $field . ' = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = :brand_id')
                ->where('brands_cars.catname = :brand_car_catname');

            $currentCar = $db->fetchRow($select, array(
                'lang'              => $language,
                'brand_id'          => (int)$brand['id'],
                'brand_car_catname' => (string)$this->getParam('car_catname')
            ));

            if (!$currentCar) {
                return $this->forward('notfound', 'error');
            }

            $carFullName = Cars_Row::buildFullName($currentCar);

            // prefetch car names
            $ids = array();
            if (count($path)) {
                $ids[] = $currentCar['top_car_id'];
            }
            foreach ($path as $idx => $pathNode) {
                $ridx = count($path) - $idx - 1;
                $idKey = 'cp_'.$ridx.'_car_id';
                $nameKey = 'cp_'.$ridx.'_name';

                if (!$currentCar[$nameKey]) {
                    $ids[] = $currentCar[$idKey];
                }
            }
            $carNames = $this->getCarNames($ids);


            // breadcrumbs
            $breadcrumbs = array();
            $breadcrumbsPath = array();

            $topCarName = null;
            if (count($path)) {
                if (isset($carNames[$currentCar['top_car_id']])) {
                    $topCarName = $carNames[$currentCar['top_car_id']];
                }
            } else {
                $topCarName = $carFullName;
            }


            $breadcrumbs[] = array(
                'name' => $this->stripName($brand, $topCarName),
                'url'  => $this->_helper->url->url(array(
                    'module'        => 'default',
                    'controller'    => 'catalogue',
                    'action'        => 'brand-car',
                    'brand_catname' => $brand['catname'],
                    'car_catname'   => $currentCar['brand_car_catname'],
                    'path'          => $breadcrumbsPath
                ), 'catalogue', true)
            );

            foreach ($path as $idx => $pathNode) {
                $ridx = count($path) - $idx - 1;
                $nameKey = 'cp_'.$ridx.'_name';
                $idKey = 'cp_'.$ridx.'_car_id';

                $breadcrumbName = $currentCar[$nameKey];
                if (!$breadcrumbName) {
                    $carId = $currentCar[$idKey];
                    if (isset($carNames[$carId])) {
                        $breadcrumbName = $this->stripName($brand, $carNames[$carId]);
                    }
                }

                $breadcrumbsPath[] = $pathNode;

                $breadcrumbs[] = array(
                    'name' => $breadcrumbName,
                    'url'  => $this->_helper->url->url(array(
                        'module'        => 'default',
                        'controller'    => 'catalogue',
                        'action'        => 'brand-car',
                        'brand_catname' => $brand['catname'],
                        'car_catname'   => $currentCar['brand_car_catname'],
                        'path'          => $breadcrumbsPath
                    ), 'catalogue', true)
                );
            }

            $design = false;

            // new design projects
            $designCarsRow = $db->fetchRow(
                $db->select()
                    ->from('brands', [
                        'brand_name'    => 'caption',
                        'brand_catname' => 'folder'
                    ])
                    ->join('brands_cars', 'brands.id = brands_cars.brand_id', [
                        'brand_car_catname' => 'catname'
                    ])
                    ->where('brands_cars.type = ?', Brands_Cars::TYPE_DESIGN)
                    ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', 'car_id')
                    ->where('car_parent_cache.car_id = ?', $currentCar['id'])
            );
            if ($designCarsRow) {
                $design = [
                    'name' => $designCarsRow['brand_name'],
                    'url'  => $this->_helper->url->url([
                        'controller'    => 'catalogue',
                        'action'        => 'brand-car',
                        'brand_catname' => $designCarsRow['brand_catname'],
                        'car_catname'   => $designCarsRow['brand_car_catname']
                    ], 'catalogue', true)
                ];
            }

            $this->view->assign(array(
                'design'       => $design,
                'carFullName'  => $carFullName,
                'carShortName' => $this->getCarShortName($brand, $carFullName),
                'carCatname'   => $currentCar['brand_car_catname'],
            ));

            $this->_helper->actionStack('brand', 'sidebar', 'default', array(
                'brand_id'    => $brand['id'],
                'car_id'      => $currentCar['top_car_id'],
                'is_concepts' => $currentCar['is_concept']
            ));

            return $callback($brand, $currentCar, $currentCar['brand_car_catname'], $path, $breadcrumbs);
        });
    }

    private function childsTypeCount($carId)
    {
        $carTable = $this->_helper->catalogue()->getCarTable();
        $db = $carTable->getAdapter();
        $select = $db->select()
            ->from('car_parent', array('type', 'count(1)'))
            ->where('parent_id = ?', $carId)
            ->group('type');

        $pairs = $db->fetchPairs($select);

        return array(
            'stock'  => isset($pairs[Car_Parent::TYPE_DEFAULT]) ? $pairs[Car_Parent::TYPE_DEFAULT] : 0,
            'tuning' => isset($pairs[Car_Parent::TYPE_TUNING]) ? $pairs[Car_Parent::TYPE_TUNING] : 0,
            'sport'  => isset($pairs[Car_Parent::TYPE_SPORT]) ? $pairs[Car_Parent::TYPE_SPORT] : 0
        );
    }

    public function brandCarAction()
    {
        return $this->_brandCarAction(function($brand, array $currentCar, $brandCarCatname, $path, $breadcrumbs) {

            $modification = null;
            $modId = (int)$this->getParam('mod');
            if ($modId) {
                $mTable = new ModificationTable();

                $modification = $mTable->find($modId)->current();
                if (!$modification) {
                    return $this->forward('notfound', 'error');
                }
            }

            $modgroupId = (int)$this->getParam('modgroup');
            if ($modgroupId) {
                $mgTable = new Modification_Group();

                $modgroup = $mgTable->find($modgroupId)->current();
                if (!$modgroup) {
                    return $this->forward('notfound', 'error');
                }
            }

            if ($modgroupId) {
                return $this->brandCarModgroup($brand, $currentCar, $brandCarCatname, $path, $modgroupId, $modId, $breadcrumbs);
            }

            if ($currentCar['is_group']) {
                return $this->brandCarGroup($brand, $currentCar, $brandCarCatname, $path, $modgroupId, $modId, $breadcrumbs);
            }

            $type = $this->getParam('type');
            switch ($type) {
                case 'tuning':
                    $type = Car_Parent::TYPE_TUNING;
                    break;
                case 'sport':
                    $type = Car_Parent::TYPE_SPORT;
                    break;
                default:
                    $type = Car_Parent::TYPE_DEFAULT;
                    break;
            }

            $carTable = $this->_helper->catalogue()->getCarTable();
            $carParentTable = new Car_Parent();

            $currentCarId = $currentCar['id'];

            $listCars = $carTable->find($currentCarId);

            $currentPictures = array();
            $currentPicturesCount = 0;

            $canAcceptPicture = $this->_helper->user()->isAllowed('picture', 'accept');

            $inboxCount = 0;
            if ($canAcceptPicture) {
                $inboxCount = $this->getCarInboxCount($currentCarId);
            }

            $requireAttention = 0;
            $isModerator = $this->_helper->user()->inheritsRole('moder');
            if ($isModerator) {
                $requireAttention = $this->getCarModerAttentionCount($currentCarId);
            }

            $counts = $this->childsTypeCount($currentCarId);

            $modificationGroups = [];

            $description = null;
            if ($currentCar['text_id']) {
                $textStorage = $this->_helper->textStorage();
                $description = $textStorage->getText($currentCar['text_id']);
            }
            $currentCar['description'] = $description;

            $text = null;
            if ($currentCar['full_text_id']) {
                $textStorage = $this->_helper->textStorage();
                $text = $textStorage->getText($currentCar['full_text_id']);
            }
            $currentCar['text'] = $text;
            $hasHtml = (bool)$currentCar['text'];

            $this->view->assign(array(
                'car'           => $currentCar,
                'modificationGroups' => $this->_brandCarModifications($currentCar['id'], $modId),
                'breadcrumbs'   => $breadcrumbs,
                'type'          => $type,
                'stockCount'    => $counts['stock'],
                'tuningCount'   => $counts['tuning'],
                'sportCount'    => $counts['sport'],
                'picturesCount' => 0,
                'hasHtml'       => $hasHtml,
                'currentPictures'      => $currentPictures,
                'currentPicturesCount' => $currentPicturesCount,
                'currentPicturesUrl'   => $this->_helper->url->url(array(
                    'module'        => 'default',
                    'controller'    => 'catalogue',
                    'action'        => 'brand-car-pictures',
                    'brand_catname' => $brand['catname'],
                    'car_catname'   => $brandCarCatname,
                    'path'          => $path,
                    'exact'         => true
                )),
                'childListData' => $this->_helper->car->listData($listCars, array(
                    'disableDescription' => true,
                    'type'       => $type == Car_Parent::TYPE_DEFAULT ? $type : null,
                    'detailsUrl' => false,
                    'allPicturesUrl' => function($listCar) use ($brand, $brandCarCatname, $path) {
                        return $this->_helper->url->url(array(
                            'module'        => 'default',
                            'controller'    => 'catalogue',
                            'action'        => 'brand-car-pictures',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $brandCarCatname,
                            'path'          => $path,
                            'exact'         => true
                        ), 'catalogue', true);
                    },
                    'onlyExactlyPictures' => true,
                    'specificationsUrl' => function($listCar) use ($brand, $brandCarCatname, $path) {

                        $specService = new Application_Service_Specifications();
                        $hasSpecs = $specService->hasSpecs(1, $listCar->id);

                        if (!$hasSpecs) {
                            return false;
                        }

                        return $this->_helper->url->url(array(
                            'module'        => 'default',
                            'controller'    => 'catalogue',
                            'action'        => 'brand-car-specifications',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $brandCarCatname,
                            'path'          => $path
                        ), 'catalogue', true);
                    },
                    'typeUrl' => function($listCar, $type) use($carParentTable, $currentCarId, $path) {

                        switch ($type) {
                            case Car_Parent::TYPE_TUNING:
                                $catname = 'tuning';
                                break;
                            case Car_Parent::TYPE_SPORT:
                                $catname = 'sport';
                                break;
                            default:
                                throw new Exception('Unexpected type');
                                break;
                        }

                        $carParentRow = $carParentTable->fetchRow(array(
                            'car_id = ?'    => $listCar->id,
                            'parent_id = ?' => $currentCarId
                        ));
                        if ($carParentRow) {
                            $currentPath = array_merge($path, array(
                                $carParentRow->catname
                            ));
                        } else {
                            $currentPath = $path;
                        }

                        return $this->_helper->url->url(array(
                            'path' => $currentPath,
                            'type' => $catname,
                            'page' => null,
                        ));
                    },
                    'pictureUrl' => function($listCar, $picture) use ($brand, $currentCarId, $brandCarCatname, $path, $carParentTable) {

                        return $this->_helper->url->url(array(
                            'module'        => 'default',
                            'controller'    => 'catalogue',
                            'action'        => 'brand-car-picture',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $brandCarCatname,
                            'path'          => $path,
                            'picture_id'    => $picture['identity'] ? $picture['identity'] : $picture['id']
                        ), 'catalogue', true);
                    }
                )),
                'canAcceptPicture' => $canAcceptPicture,
                'inboxCount'       => $inboxCount,
                'requireAttention' => $requireAttention
            ));
        });
    }

    private function brandCarGroupModifications($carId, $groupId, $modificationId)
    {
        $mTable = new ModificationTable();
        $db = $mTable->getAdapter();

        $select = $mTable->select(true)
            ->join('car_parent_cache', 'modification.car_id = car_parent_cache.parent_id', null)
            ->where('car_parent_cache.car_id = ?', $carId)
            ->order('modification.name');

        if ($groupId) {
            $select->where('modification.group_id = ?', $groupId);
        } else {
            $select->where('modification.group_id IS NULL');
        }

        $modifications = [];
        foreach ($mTable->fetchAll($select) as $mRow) {
            $modifications[] = array(
                'name'      => $mRow->name,
                'url'       => $this->_helper->url->url(array(
                    'action' => 'brand-car', // -pictures
                    'mod'    => $mRow->id,
                )),
                'count'     => $db->fetchOne(
                    $db->select()
                        ->from('modification_picture', 'count(1)')
                        ->where('modification_picture.modification_id = ?', $mRow->id)
                        ->join('pictures', 'modification_picture.picture_id = pictures.id', null)
                        ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                        ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                        ->where('car_parent_cache.parent_id = ?', $carId)
                ),
                'active' => $mRow->id == $modificationId
            );
        }

        return $modifications;
    }

    private function _brandCarModifications($carId, $modificationId)
    {
        // modifications
        $mTable = new ModificationTable();
        $mgTable = new Modification_Group();

        $modificationGroups = [];

        $mgRows = $mgTable->fetchAll(
            $mgTable->select(true)
                ->join('modification', 'modification_group.id = modification.group_id', null)
                ->join('car_parent_cache', 'modification.car_id = car_parent_cache.parent_id', null)
                ->where('car_parent_cache.car_id = ?', $carId)
                ->group('modification_group.id')
                ->order('modification_group.name')
        );

        $groups = [];
        foreach ($mgRows as $mgRow) {
            $modifications = $this->brandCarGroupModifications($carId, $mgRow->id, $modificationId);

            if ($modifications) {
                $modificationGroups[] = [
                    'name'          => $mgRow->name,
                    'modifications' => $modifications,
                    'url'           => $this->_helper->url->url([
                        'action'   => 'brand-car',
                        'modgroup' => $mgRow->id,
                    ])
                ];
            }
        }

        $modifications = $this->brandCarGroupModifications($carId, null, $modificationId);
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
        $pictureTable = $this->_helper->catalogue()->getPictureTable();
        $db = $pictureTable->getAdapter();

        return $db->select()
            ->from(
                $pictureTable->info('name'),
                [
                    'id', 'name', 'type', 'brand_id', 'engine_id', 'car_id', 'factory_id',
                    'perspective_id', 'image_id', 'crop_left', 'crop_top',
                    'crop_width', 'crop_height', 'width', 'height', 'identity', 'factory_id'
                ]
            )
            ->where('pictures.status IN (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
            ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
            ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
            ->where('car_parent_cache.parent_id = ?', $carId)
            ->join('modification_picture', 'pictures.id = modification_picture.picture_id', null)
            ->where('modification_picture.modification_id = ?', $modId)
            ->limit(1);
    }

    private function getModgroupPictureList($carId, $modId, array $perspectiveGroupIds)
    {
        $pictures = [];
        $usedIds = [];

        $pictureTable = $this->_helper->catalogue()->getPictureTable();
        $db = $pictureTable->getAdapter();

        foreach ($perspectiveGroupIds as $groupId) {

            $select = $this->getModgroupPicturesSelect($carId, $modId)
                ->join(['mp' => 'perspectives_groups_perspectives'], 'pictures.perspective_id = mp.perspective_id', null)
                ->where('mp.group_id = ?', $groupId)
                ->order([
                    //'cars.is_concept asc',
                    'car_parent_cache.sport asc',
                    'car_parent_cache.tuning asc',
                    'mp.position'
                ])
                ->limit(1);

            /*
            if (isset($options['type'])) {
                switch ($options['type']) {
                    case Car_Parent::TYPE_DEFAULT:
                        break;
                    case Car_Parent::TYPE_TUNING:
                        $select->where('car_parent_cache.tuning');
                        break;
                    case Car_Parent::TYPE_SPORT:
                        $select->where('car_parent_cache.sport');
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
            if (!$picture) {
                $select = $this->getModgroupPicturesSelect($carId, $modId)->limit(1)
                    ->order([
                        //'cars.is_concept asc',
                        'car_parent_cache.sport asc',
                        'car_parent_cache.tuning asc'
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

        $needMore = count($perspectiveGroupIds) - count($usedIds);

        $result = array();
        foreach ($pictures as $idx => $picture) {
            if ($picture) {
                $pictureId = $picture['id'];

                $format = 'picture-thumb';

                $url = $this->_helper->pic->href($picture);

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
        $perspectivesGroups = new Perspectives_Groups();
        $db = $perspectivesGroups->getAdapter();
        return $db->fetchCol(
            $db->select()
                ->from($perspectivesGroups->info('name'), 'id')
                ->where('page_id = ?', $pageId)
                ->order('position')
        );
    }

    private function brandCarModgroup($brand, array $currentCar, $brandCarCatname, $path, $modgroupId, $modId, $breadcrumbs)
    {
        $currentCarId = $currentCar['id'];

        $mTable = new ModificationTable();
        $pictureTable = $this->_helper->catalogue()->getPictureTable();
        $imageStorage = $this->getInvokeArg('bootstrap')->getResource('imagestorage');

        $language = $this->_helper->language();

        $catalogue = $this->_helper->catalogue();

        $g = $this->getPerspectiveGroupIds(2);

        $select = $mTable->select(true)
            ->join('car_parent_cache', 'modification.car_id = car_parent_cache.parent_id', null)
            ->where('car_parent_cache.car_id = ?', $currentCarId)
            ->where('modification.group_id = ?', $modgroupId)
            ->group('modification.id')
            ->order('modification.name');

        $modifications = [];
        foreach ($mTable->fetchAll($select) as $modification) {

            $pictures = [];

            $pictureRows = $this->getModgroupPictureList($currentCarId, $modification['id'], $g);
            $select = $this->getModgroupPicturesSelect($currentCarId, $modification['id']);
            $pPaginator = Zend_Paginator::factory($select);

            foreach ($pictureRows as $pictureRow) {
                if ($pictureRow) {
                    $request = $catalogue->getPictureFormatRequest($pictureRow['row']);
                    $imageInfo = $imageStorage->getFormatedImage($request, 'picture-thumb');

                    $pictures[] = [
                        'src'  => $imageInfo ? $imageInfo->getSrc() : null,
                        'url'  => $this->_helper->url->url([
                            'action'        => 'brand-car-picture',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $brandCarCatname,
                            'path'          => $path,
                            'exact'         => false,
                            'picture_id'    => $pictureRow['row']['identity'] ? $pictureRow['row']['identity'] : $pictureRow['row']['id']
                        ])
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
                if (!isset($nameParam)) {
                    unset($nameParams[$key]);
                }
            }
            unset($nameParam);

            $modifications[] = [
                'nameParams' => $nameParams,
                'name'       => $modification['name'],
                'url'        => $this->_helper->url->url([
                    'action' => 'brand-car-pictures',
                    'mod'    => $modification['id']
                ]),
                'pictures'      => $pictures,
                'totalPictures' => $pPaginator->getTotalItemCount()
            ];
        }

        $canAcceptPicture = $this->_helper->user()->isAllowed('picture', 'accept');

        $inboxCount = 0;
        if ($canAcceptPicture) {
            $inboxCount = $this->getCarInboxCount($currentCarId);
        }

        $requireAttention = 0;
        $isModerator = $this->_helper->user()->inheritsRole('moder');
        if ($isModerator) {
            $requireAttention = $this->getCarModerAttentionCount($currentCarId);
        }

        $description = null;
        if ($currentCar['text_id']) {
            $textStorage = $this->_helper->textStorage();
            $description = $textStorage->getText($currentCar['text_id']);
        }
        $currentCar['description'] = $description;

        $text = null;
        if ($currentCar['full_text_id']) {
            $textStorage = $this->_helper->textStorage();
            $text = $textStorage->getText($currentCar['full_text_id']);
        }
        $currentCar['text'] = $text;
        $hasHtml = (bool)$currentCar['text'];

        $this->view->assign([
            'modificationGroups' => $this->_brandCarModifications($currentCar['id'], $modId),
            'modgroup'         => true,
            'breadcrumbs'      => $breadcrumbs,
            'car'              => $currentCar,
            'modifications'    => $modifications,
            'canAcceptPicture' => $canAcceptPicture,
            'inboxCount'       => $inboxCount,
            'requireAttention' => $requireAttention,
            'hasHtml'          => $hasHtml,
            'isCarModer'       => $this->_helper->user()->inheritsRole('cars-moder')
        ]);
    }

    private function brandCarGroup($brand, array $currentCar, $brandCarCatname, $path, $modgroupId, $modId, $breadcrumbs)
    {
        $currentCarId = $currentCar['id'];

        $type = $this->getParam('type');
        switch ($type) {
            case 'tuning':
                $type = Car_Parent::TYPE_TUNING;
                break;
            case 'sport':
                $type = Car_Parent::TYPE_SPORT;
                break;
            default:
                $type = Car_Parent::TYPE_DEFAULT;
                break;
        }

        $carTable = $this->_helper->catalogue()->getCarTable();
        $carParentTable = new Car_Parent();
        $db = $carParentTable->getAdapter();

        $listCars = [];

        $select = $carTable->select(true)
            ->join('car_parent', 'cars.id = car_parent.car_id', null)
            ->where('car_parent.parent_id = ?', $currentCarId)
            ->where('car_parent.type = ?', $type)
            ->order($this->carsOrder());

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(20)
            ->setCurrentPageNumber($this->getParam('page'));

        $isLastPage = $paginator->getCurrentPageNumber() == $paginator->count() || !$paginator->count();

        foreach ($paginator->getCurrentItems() as $row) {
            $listCars[] = $row;
        }

        $currentPictures = [];
        $currentPicturesCount = 0;
        if ($isLastPage && $type == Car_Parent::TYPE_DEFAULT) {
            $pictureTable = $this->_helper->catalogue()->getPictureTable();
            $select = $this->selectOrderFromPictures()
                ->where('pictures.car_id = ?', $currentCarId)
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID);
            $pPaginator = Zend_Paginator::factory($select)
                ->setItemCountPerPage(4);

            $imageStorage = $this->getInvokeArg('bootstrap')->getResource('imagestorage');
            $language = $this->_helper->language();

            $currentPictures = [];

            foreach ($pPaginator->getCurrentItems() as $pictureRow) {
                $imageInfo = $imageStorage->getFormatedImage($pictureRow->getFormatRequest(), 'picture-thumb');

                $currentPictures[] = array(
                    'name' => $pictureRow->getCaption([
                        'language' => $language
                    ]),
                    'src'  => $imageInfo ? $imageInfo->getSrc() : null,
                    'url'  => $this->_helper->url->url([
                        'action'        => 'brand-car-picture',
                        'brand_catname' => $brand['catname'],
                        'car_catname'   => $brandCarCatname,
                        'path'          => $path,
                        'exact'         => true,
                        'picture_id'    => $pictureRow['identity'] ? $pictureRow['identity'] : $pictureRow['id']
                    ])
                );
            }

            $currentPicturesCount = $pPaginator->getTotalItemCount();
        }

        $canAcceptPicture = $this->_helper->user()->isAllowed('picture', 'accept');

        $inboxCount = 0;
        if ($canAcceptPicture) {
            $inboxCount = $this->getCarInboxCount($currentCarId);
        }

        $requireAttention = 0;
        $isModerator = $this->_helper->user()->inheritsRole('moder');
        if ($isModerator) {
            $requireAttention = $this->getCarModerAttentionCount($currentCarId);
        }

        $ids = array();
        foreach ($listCars as $car) {
            $ids[] = $car->id;
        }

        $specService = new Application_Service_Specifications();
        $hasChildSpecs = $specService->hasChildSpecs(1, $ids);

        $picturesSelect = $this->selectFromPictures()
            ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
            ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
            ->where('car_parent_cache.parent_id = ?', $currentCarId);

        $counts = $this->childsTypeCount($currentCarId);


        $description = null;
        if ($currentCar['text_id']) {
            $textStorage = $this->_helper->textStorage();
            $description = $textStorage->getText($currentCar['text_id']);
        }
        $currentCar['description'] = $description;

        $text = null;
        if ($currentCar['full_text_id']) {
            $textStorage = $this->_helper->textStorage();
            $text = $textStorage->getText($currentCar['full_text_id']);
        }
        $currentCar['text'] = $text;
        $hasHtml = (bool)$currentCar['text'];

        $this->view->assign(array(
            'car'           => $currentCar,
            'modificationGroups' => $this->_brandCarModifications($currentCar['id'], $modId),
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
            'currentPicturesUrl'   => $this->_helper->url->url(array(
                'module'        => 'default',
                'controller'    => 'catalogue',
                'action'        => 'brand-car-pictures',
                'brand_catname' => $brand['catname'],
                'car_catname'   => $brandCarCatname,
                'path'          => $path,
                'exact'         => true,
                'page'          => null
            )),
            'childListData' => $this->_helper->car->listData($listCars, array(
                'disableDescription' => false,
                'type'       => $type == Car_Parent::TYPE_DEFAULT ? $type : null,
                'detailsUrl' => function($listCar) use ($brand, $currentCarId, $brandCarCatname, $path, $carParentTable) {

                    $carParentAdapter = $carParentTable->getAdapter();
                    $hasChilds = (bool)$carParentAdapter->fetchOne(
                        $carParentAdapter->select()
                            ->from($carParentTable->info('name'), new Zend_Db_Expr('1'))
                            ->where('parent_id = ?', $listCar->id)
                    );

                    $hasHtml = (bool)$listCar->full_text_id;

                    if (!$hasChilds && !$hasHtml) {
                        return false;
                    }

                    // found parent row
                    $carParentRow = $carParentTable->fetchRow(array(
                        'car_id = ?'    => $listCar->id,
                        'parent_id = ?' => $currentCarId
                    ));
                    if (!$carParentRow) {
                        return false;
                    }

                    $currentPath = array_merge($path, array(
                        $carParentRow->catname
                    ));

                    return $this->_helper->url->url(array(
                        'module'        => 'default',
                        'controller'    => 'catalogue',
                        'action'        => 'brand-car',
                        'brand_catname' => $brand['catname'],
                        'car_catname'   => $brandCarCatname,
                        'path'          => $currentPath
                    ), 'catalogue', true);
                },
                'allPicturesUrl' => function($listCar) use ($brand, $brandCarCatname, $path, $currentCarId, $carParentTable) {

                    //TODO: more than 1 levels diff fails here
                    $carParentRow = $carParentTable->fetchRow(array(
                        'car_id = ?'    => $listCar->id,
                        'parent_id = ?' => $currentCarId
                    ));
                    if ($carParentRow) {
                        $currentPath = array_merge($path, array(
                            $carParentRow->catname
                        ));
                        return $this->_helper->url->url(array(
                            'module'        => 'default',
                            'controller'    => 'catalogue',
                            'action'        => 'brand-car-pictures',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $brandCarCatname,
                            'path'          => $currentPath,
                            'exact'         => false
                        ), 'catalogue', true);
                    }

                    return false;
                },
                'onlyExactlyPictures' => false,
                'specificationsUrl' => function($listCar) use ($brand, $specService, $hasChildSpecs, $carParentTable, $brandCarCatname, $path, $currentCarId, $type) {
                    if ($hasChildSpecs[$listCar->id]) {
                        $carParentRow = $carParentTable->fetchRow(array(
                            'car_id = ?'    => $listCar->id,
                            'parent_id = ?' => $currentCarId
                        ));
                        if ($carParentRow) {
                            $currentPath = array_merge($path, array(
                                $carParentRow->catname
                            ));

                            return $this->_helper->url->url(array(
                                'module'        => 'default',
                                'controller'    => 'catalogue',
                                'action'        => 'brand-car-specifications',
                                'brand_catname' => $brand['catname'],
                                'car_catname'   => $brandCarCatname,
                                'path'          => $currentPath,
                            ), 'catalogue', true);
                        }
                    }

                    if (!$specService->hasSpecs(1, $listCar->id)) {
                        return false;
                    }

                    switch($type) {
                        case Car_Parent::TYPE_TUNING:
                            $typeStr = 'tuning';
                            break;

                        case Car_Parent::TYPE_SPORT:
                            $typeStr = 'sport';
                            break;

                        default:
                            $typeStr = null;
                            break;
                    }

                    return $this->_helper->url->url(array(
                        'module'        => 'default',
                        'controller'    => 'catalogue',
                        'action'        => 'brand-car-specifications',
                        'brand_catname' => $brand['catname'],
                        'car_catname'   => $brandCarCatname,
                        'path'          => $path,
                        'type'          => $typeStr
                    ), 'catalogue', true);
                },
                'typeUrl' => function($listCar, $type) use($carParentTable, $currentCarId, $path) {

                    switch ($type) {
                        case Car_Parent::TYPE_TUNING:
                            $catname = 'tuning';
                            break;
                        case Car_Parent::TYPE_SPORT:
                            $catname = 'sport';
                            break;
                        default:
                            throw new Exception('Unexpected type');
                            break;
                    }

                    $carParentRow = $carParentTable->fetchRow(array(
                        'car_id = ?'    => $listCar->id,
                        'parent_id = ?' => $currentCarId
                    ));
                    if ($carParentRow) {
                        $currentPath = array_merge($path, array(
                            $carParentRow->catname
                        ));
                    } else {
                        $currentPath = $path;
                    }

                    return $this->_helper->url->url(array(
                        'path' => $currentPath,
                        'type' => $catname,
                        'page' => null,
                    ));
                },
                'pictureUrl' => function($listCar, $picture) use ($brand, $currentCarId, $brandCarCatname, $path, $carParentTable) {

                    // found parent row
                    $carParentRow = $carParentTable->fetchRow(array(
                        'car_id = ?'    => $listCar->id,
                        'parent_id = ?' => $currentCarId
                    ));
                    if (!$carParentRow) {
                        return $this->_helper->pic->url($picture['id'], $picture['identity']);
                    }

                    $currentPath = array_merge($path, array(
                        $carParentRow->catname
                    ));

                    return $this->_helper->url->url(array(
                        'module'        => 'default',
                        'controller'    => 'catalogue',
                        'action'        => 'brand-car-picture',
                        'brand_catname' => $brand['catname'],
                        'car_catname'   => $brandCarCatname,
                        'path'          => $currentPath,
                        'picture_id'    => $picture['identity'] ? $picture['identity'] : $picture['id']
                    ), 'catalogue', true);
                }
            )),
            'canAcceptPicture' => $canAcceptPicture,
            'inboxCount'       => $inboxCount,
            'requireAttention' => $requireAttention
        ));
    }

    private function getCarModerAttentionCount($carId)
    {
        $commentTable = new Comment_Message();

        $select = $commentTable->select(true)
            ->where('comments_messages.moderator_attention = ?', Comment_Message::MODERATOR_ATTENTION_REQUIRED)
            ->where('comments_messages.type_id = ?', Comment_Message::PICTURES_TYPE_ID)
            ->join('pictures', 'comments_messages.item_id = pictures.id', null)
            ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
            ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
            ->where('car_parent_cache.parent_id = ?', $carId);

        return Zend_Paginator::factory($select)->getTotalItemCount();
    }

    private function getCarInboxCount($carId)
    {
        $pictureTable = $this->_helper->catalogue()->getPictureTable();
        $select = $pictureTable->select(true)
            ->where('pictures.status = ?', Picture::STATUS_INBOX)
            ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
            ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
            ->where('car_parent_cache.parent_id = ?', $carId);

        return Zend_Paginator::factory($select)->getTotalItemCount();
    }

    /**
     * @param int $carId
     * @param bool $exact
     * @return Zend_Db_Table_Select
     */
    private function getBrandCarPicturesSelect($carId, $exact, $onlyAccepted = true)
    {
        $select = $this->selectOrderFromPictures($onlyAccepted)
            ->where('pictures.type = ?', Picture::CAR_TYPE_ID);

        if ($exact) {
            $select
                ->where('pictures.car_id = ?', $carId);
        } else {
            $select
                ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                ->where('car_parent_cache.parent_id = ?', $carId);
        }

        return $select;
    }

    public function brandCarPicturesAction()
    {
        return $this->_brandCarAction(function($brand, array $currentCar, $brandCarCatname, $path, $breadcrumbs) {

            $exact = (bool)$this->getParam('exact');

            $select = $this->getBrandCarPicturesSelect($currentCar['id'], $exact);

            $modification = null;
            $modId = (int)$this->getParam('mod');
            if ($modId) {
                $mTable = new ModificationTable();

                $modification = $mTable->find($modId)->current();
                if (!$modification) {
                    return $this->forward('notfound', 'error');
                }

                $select
                    ->join('modification_picture', 'pictures.id = modification_picture.picture_id', null)
                    ->where('modification_picture.modification_id = ?', $modId);
            }

            $paginator = $this->picturesPaginator($select, $this->getParam('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->forward('notfound', 'error');
            }

            $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

            $picturesData = $this->_helper->pic->listData($select, array(
                'width' => 4,
                'url'   => function($row) use($brand, $brandCarCatname, $path, $exact) {
                    return $this->_helper->url->url(array(
                        'action'        => 'brand-car-picture',
                        'brand_catname' => $brand['catname'],
                        'car_catname'   => $brandCarCatname,
                        'path'          => $path,
                        'exact'         => $exact,
                        'picture_id'    => $row['identity'] ? $row['identity'] : $row['id']
                    ));
                }
            ));

            $counts = $this->childsTypeCount($currentCar['id']);

            $this->view->assign(array(
                'breadcrumbs'   => $breadcrumbs,
                'picturesData'  => $picturesData,
                'paginator'     => $paginator,
                'stockCount'    => $counts['stock'],
                'tuningCount'   => $counts['tuning'],
                'sportCount'    => $counts['sport'],
                'picturesCount' => $paginator->getTotalItemCount(),
                'type'          => null,
                'modification'  => $modification,
                'modificationGroups' => $this->_brandCarModifications($currentCar['id'], $modId),
            ));
        });
    }

    private function _pictureAction($select, Callable $callback)
    {
        $pictureId = (string)$this->getParam('picture_id');

        $picture = $this->fetchSelectPicture($select, $pictureId);
        if (!$picture) {
            return $this->forward('notfound', 'error');
        }

        $isModer = $this->_helper->user()->inheritsRole('moder');

        if ($picture->status == Picture::STATUS_REMOVING) {
            $user = $this->_helper->user()->get();
            if (!$user) {
                return $this->forward('notfound', 'error');
            }

            if ($isModer || ($user->id == $picture->owner_id)) {
                $this->getResponse()->setHttpResponseCode(404);
            } else {
                return $this->forward('notfound', 'error');
            }

            $select->where('pictures.status = ?', Picture::STATUS_REMOVING);

        } elseif ($picture->status == Picture::STATUS_INBOX) {

            $select->where('pictures.status = ?', Picture::STATUS_INBOX);

        } else {

            $select->where('pictures.status IN (?)', array(
                Picture::STATUS_NEW, Picture::STATUS_ACCEPTED
            ));

        }

        $callback($select, $picture);
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

    public function brandCarPictureAction()
    {
        return $this->_brandCarAction(function($brand, array $currentCar, $brandCarCatname, $path, $breadcrumbs) {

            $exact = (bool)$this->getParam('exact');

            $select = $this->getBrandCarPicturesSelect($currentCar['id'], $exact, false);

            $this->_pictureAction($select, function($select, $picture) use ($breadcrumbs) {
                $this->view->assign(array(
                    'breadcrumbs' => $breadcrumbs,
                    'picture'     => array_replace(
                        $this->_helper->pic->picPageData($picture, $select),
                        array(
                            'gallery2'   => true,
                            'galleryUrl' => $this->_helper->url->url(array(
                                'action'  => 'brand-car-gallery',
                                'gallery' => $this->galleryType($picture)
                            ))
                        )
                    )
                ));
            });
        });
    }

    public function brandCarGalleryAction()
    {
        return $this->_brandCarAction(function($brand, array $currentCar, $brandCarCatname, $path, $breadcrumbs) {

            $exact = (bool)$this->getParam('exact');
            $select = $this->getBrandCarPicturesSelect($currentCar['id'], $exact, false);

            switch ($this->getParam('gallery')) {
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

            return $this->_helper->json($this->_helper->pic->gallery2($select, array(
                'page'      => $this->getParam('page'),
                'pictureId' => $this->getParam('pictureId'),
                'urlParams' => array(
                    'action' => 'brand-car-picture'
                )
            )));
        });
    }

    public function brandCarSpecificationsAction()
    {
        return $this->_brandCarAction(function($brand, array $currentCar, $brandCarCatname, $path, $breadcrumbs) {

            $currentCarId = $currentCar['id'];

            $type = $this->getParam('type');
            switch ($type) {
                case 'tuning':
                    $type = Car_Parent::TYPE_TUNING;
                    break;
                case 'sport':
                    $type = Car_Parent::TYPE_SPORT;
                    break;
                default:
                    $type = Car_Parent::TYPE_DEFAULT;
                    break;
            }

            //$list = $this->_helper->catalogue()->getCarTable()->find($brandCarRow->car_id);

            $carTable = $this->_helper->catalogue()->getCarTable();

            $select = $carTable->select(true)
                ->order($this->carsOrder());
            if ($currentCar['is_group']) {
                $select
                    ->where('car_parent.type = ?', $type)
                    ->join('car_parent', 'cars.id = car_parent.car_id', null)
                    ->where('car_parent.parent_id = ?', $currentCarId);

            } else {
                $select
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                    ->where('car_parent_cache.parent_id = ?', $currentCarId)
                    ->where('car_parent_cache.diff <= 1');
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
                        ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                        ->where('car_parent_cache.parent_id = ?', $currentCarId);
                }

                $childCars = $carTable->fetchAll($select);
            }

            $service = new Application_Service_Specifications();

            $cars = array();
            foreach ($childCars as $childCar) {
                if ($service->hasSpecs(1, $childCar->id)) {
                    $cars[] = $childCar;
                }
            }

            $user = $this->_helper->user()->get();


            $specs = $service->specifications($cars, array(
                'language'     => 'en',
                'contextCarId' => $currentCarId
            ));

            $ids = array();
            foreach ($cars as $car) {
                $ids[] = $car->id;
            }

            $contribPairs = $service->getContributors(1, $ids);

            $userTable = new Users();
            $contributors = $userTable->find(array_keys($contribPairs));

            $this->view->assign(array(
                'breadcrumbs'  => $breadcrumbs,
                'specs'        => $specs,
                'contributors' => $contributors
            ));
        });
    }

    private function mostsActive($brandId)
    {
        $carTable = new Cars();
        $db = $carTable->getAdapter();
        $carsCount = $db->fetchOne(
            $db->select()
                ->from($carTable->info('name'), 'count(1)')
                ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', (int)$brandId)
        );

        return $carsCount >= $this->_mostsMinCarsCount;
    }

    public function brandMostsAction()
    {
        $this->_brandAction(function($brand) {

            if (!$this->mostsActive($brand['id'])) {
                return $this->forward('notfound', 'error');
            }

            $specService = new Application_Service_Specifications();
            $service = new Mosts(array(
                'specs' => $specService
            ));

            $language = $this->_helper->language();
            $yearsCatname = $this->getParam('years_catname');
            $carTypeCatname = $this->getParam('shape_catname');
            $mostCatname = $this->getParam('most_catname');

            $data = $service->getData(array(
                'language' => $language,
                'most'     => $mostCatname,
                'years'    => $yearsCatname,
                'carType'  => $carTypeCatname,
                'brandId'  => $brand['id']
            ));

            foreach ($data['sidebar']['mosts'] as &$most) {
                $most['url'] = $this->_helper->url->url(
                    array_merge(
                        $most['params'],
                        ['brand_catname' => $brand['catname']]
                    ),
                    'catalogue'
                );
            }
            foreach ($data['sidebar']['carTypes'] as &$carType) {
                $carType['url'] = $this->_helper->url->url(
                    array_merge(
                        $carType['params'],
                        ['brand_catname' => $brand['catname']]
                    ),
                    'catalogue'
                );
                foreach ($carType['childs'] as &$child) {
                    $child['url'] = $this->_helper->url->url(
                        array_merge(
                            $child['params'],
                            ['brand_catname' => $brand['catname']]
                        ),
                        'catalogue'
                    );
                }
            }
            foreach ($data['years'] as &$year) {
                $year['url'] = $this->_helper->url->url(
                    array_merge(
                        $year['params'],
                        ['brand_catname' => $brand['catname']]
                    ),
                    'catalogue'
                );
            }

            // images
            $formatRequests = array();
            $allPictures = array();
            $idx = 0;
            foreach ($data['carList']['cars'] as $car) {
                foreach ($car['pictures'] as $picture) {
                    if ($picture) {
                        $formatRequests[$idx++] = $picture->getFormatRequest();
                        $allPictures[] = $picture->toArray();
                    }
                }
            }

            $imageStorage = $this->_helper->imageStorage();
            $imagesInfo = $imageStorage->getFormatedImages($formatRequests, 'picture-thumb');

            $pictureTable = new Picture();
            $names = $pictureTable->getNameData($allPictures, array(
                'language' => $language
            ));

            $carParentTable = new Car_Parent();

            $idx = 0;
            foreach ($data['carList']['cars'] as &$car) {
                $pictures = [];

                $paths = $carParentTable->getPaths($car['car']['id'], array(
                    'breakOnFirst' => true
                ));

                foreach ($car['pictures'] as $picture) {
                    if ($picture) {
                        $id = $picture->id;

                        $url = null;
                        foreach ($paths as $path) {
                            $url = $this->_helper->url->url(array(
                                'action'        => 'brand-car-picture',
                                'brand_catname' => $path['brand_catname'],
                                'car_catname'   => $path['car_catname'],
                                'path'          => $path['path'],
                                'picture_id'    => $picture['identity'] ? $picture['identity'] : $picture['id']
                            ), 'catalogue', true);
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

            $this->view->assign($data);

            $this->getResponse()->insert('sidebar', $this->view->render('most/sidebar.phtml'));
        });
    }
}