<?php

class Project_Controller_Action_Helper_Car
    extends Zend_Controller_Action_Helper_Abstract
{
    private $_perspectiveCache = array();

    /**
     * @var Car_Language
     */
    private $carLangTable;

    /**
     * @var Twins
     */
    private $_twins;

    /**
     * @var Spec
     */
    private $specTable;

    /**
     * @return Spec
     */
    private function getSpecTable()
    {
        return $this->specTable
            ? $this->specTable
            : $this->specTable = new Spec();
    }

    private function getCarLanguageTable()
    {
        return $this->carLangTable
            ? $this->carLangTable
            : $this->carLangTable = new Car_Language();
    }

    /**
     * @return Twins
     */
    private function getTwins()
    {
        return $this->_twins
            ? $this->_twins
            : $this->_twins = new Twins();
    }

    /**
     * @return Project_Controller_Action_Helper_Car
     */
    public function direct()
    {
        return $this;
    }

    private function carsTotalPictures(array $carIds, $onlyExactly)
    {
        $result = array();
        foreach ($carIds as $carId) {
            $result[$carId] = null;
        }
        if (count($carIds)) {
            $pictureTable = $this->getPictureTable();
            $pictureTableAdapter = $pictureTable->getAdapter();

            $select = $pictureTableAdapter->select()
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->where('pictures.status IN (?)', array(Picture::STATUS_NEW, Picture::STATUS_ACCEPTED));

            if ($onlyExactly) {
                $select
                    ->from($pictureTable->info('name'), array('pictures.car_id', new Zend_Db_Expr('COUNT(1)')))
                    ->where('pictures.car_id IN (?)', $carIds)
                    ->group('pictures.car_id');
            } else {
                $select
                    ->from($pictureTable->info('name'), array('car_parent_cache.parent_id', new Zend_Db_Expr('COUNT(1)')))
                    ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                    ->where('car_parent_cache.parent_id IN (?)', $carIds)
                    ->group('car_parent_cache.parent_id');
            }

            $result = array_replace($result, $pictureTableAdapter->fetchPairs($select));
        }
        return $result;
    }

    public function listData($cars, array $options = array())
    {
        $type                 = isset($options['type']) ? $options['type'] : null;
        $disableTitle         = isset($options['disableTitle']) && $options['disableTitle'];
        $disableDescription   = isset($options['disableDescription']) && $options['disableDescription'];
        $disableDetailsLink   = isset($options['disableDetailsLink']) && $options['disableDetailsLink'];
        $detailsUrl           = isset($options['detailsUrl']) ? $options['detailsUrl'] : null;
        $allPicturesUrl       = isset($options['allPicturesUrl']) && $options['allPicturesUrl'] ? $options['allPicturesUrl'] : null;
        $typeUrl              = isset($options['typeUrl']) && $options['typeUrl'] ? $options['typeUrl'] : null;
        $specificationsUrl    = isset($options['specificationsUrl']) && $options['specificationsUrl'] ? $options['specificationsUrl'] : null;
        $onlyExactlyPictures  = isset($options['onlyExactlyPictures']) ? $options['onlyExactlyPictures'] : null;
        $hideEmpty            = isset($options['hideEmpty']) && $options['hideEmpty'];
        $disableTwins         = isset($options['disableTwins']) && $options['disableTwins'];
        $disableLargePictures = isset($options['disableLargePictures']) && $options['disableLargePictures'];
        $disableSpecs         = isset($options['disableSpecs']) && $options['disableSpecs'];
        $disableCategories    = isset($options['disableCategories']) && $options['disableCategories'];
        $picturesDateSort     = isset($options['picturesDateSort']) && $options['picturesDateSort'];
        $perspectiveGroup     = isset($options['perspectiveGroup']) ? (int)$options['perspectiveGroup'] : null;
        $callback             = isset($options['callback']) && $options['callback'] ? $options['callback'] : null;
        $allowUpPictures      = isset($options['allowUpPictures']) && $options['allowUpPictures'];
        $onlyChilds           = isset($options['onlyChilds']) && is_array($options['onlyChilds']) ? $options['onlyChilds'] : array();
        $pictureUrlCallback   = isset($options['pictureUrl']) ? $options['pictureUrl'] : false;

        $controller = $this->getActionController();
        $urlHelper = $controller->getHelper('Url');
        $picHelper = $controller->getHelper('Pic');
        $userHelper = $controller->getHelper('User')->direct();
        $aclHelper = $controller->getHelper('Acl')->direct();
        $imageStorage = $controller->getHelper('imageStorage')->direct();
        $textStorage = $controller->getHelper('textStorage')->direct();

        $user = $userHelper->get();
        $specEditor = $userHelper->isAllowed('specifications', 'edit');
        $isCarModer = $userHelper->logedIn() && $aclHelper->inheritsRole($user->role, 'cars-moder');
        $language = $controller->getHelper('Language')->direct();
        $catalogue = $controller->getHelper('catalogue')->direct();

        $pictureTable = $this->getPictureTable();
        $categoryLanguageTable = new Category_Language();
        $carParentTable = new Car_Parent();
        $carParentAdapter = $carParentTable->getAdapter();
        $brandTable = new Brands();
        $brandCarTable = new Brand_Car();
        $categoryTable = new Category();

        $carIds = array();
        foreach ($cars as $car) {
            $carIds[] = (int)$car->id;
        }

        $specService = new Application_Service_Specifications();

        $hasSpecs = array();
        if (!$disableSpecs && !$specificationsUrl) {
            $hasSpecs = $specService->hasSpecs(1, $carIds);
        }

        if ($carIds) {
            $childsCounts = $carParentAdapter->fetchPairs(
                $carParentAdapter->select()
                    ->from($carParentTable->info('name'), array('parent_id', new Zend_Db_Expr('count(1)')))
                    ->where('parent_id IN (?)', $carIds)
                    ->group('parent_id')
            );
        } else {
            $childsCounts = array();
        }

        // categories
        $carsCategories = array();
        if ($carIds && !$disableCategories) {
            $db = $categoryTable->getAdapter();
            $langExpr = $db->quoteInto('category.id = category_language.category_id and category_language.language = ?', $language);
            $categoryRows = $db->fetchAll(
                $db->select()
                    ->from($categoryTable->info('name'), array('name', 'catname'))
                    ->join('category_car', 'category.id = category_car.category_id', null)
                    ->join('car_parent_cache', 'category_car.car_id = car_parent_cache.parent_id', 'car_id')
                    ->joinLeft('category_language', $langExpr, array('lang_name' => 'name'))
                    ->where('car_parent_cache.car_id IN (?)', $carIds)
                    ->group(array('car_parent_cache.car_id', 'category.id'))
            );

            foreach ($categoryRows as $category) {
                $carId = (int)$category['car_id'];
                if (!isset($carsCategories[$carId])) {
                    $carsCategories[$carId] = array();
                }
                $carsCategories[$carId][] = array(
                    'name' => $category['lang_name'] ? $category['lang_name'] : $category['name'],
                    'url'  => $urlHelper->url(array(
                        'controller'       => 'category',
                        'action'           => 'category',
                        'category_catname' => $category['catname'],
                    ), 'category', true),
                );
            }
        }

        // twins
        $carsTwinsGroups = array();
        if ($carIds && !$disableTwins) {

            $carsTwinsGroups = array();

            foreach ($this->getTwins()->getCarsGroups($carIds) as $carId => $twinsGroups) {
                $carsTwinsGroups[$carId] = array();
                foreach ($twinsGroups as $twinsGroup) {
                    $carsTwinsGroups[$carId][] = array(
                        'url'  => $urlHelper->url(array(
                            'module'         => 'default',
                            'controller'     => 'twins',
                            'action'         => 'group',
                            'twins_group_id' => $twinsGroup['id']
                        ), 'twins', true),
                    );
                }
            }
        }

        // typecount
        $carsTypeCounts = array();
        if ($carIds && $typeUrl) {
            $rows = $carParentAdapter->fetchAll(
                $carParentAdapter->select()
                    ->from($carParentTable->info('name'), array('parent_id', 'type', 'count' => 'count(1)'))
                    ->where('parent_id IN (?)', $carIds)
                    ->where('type IN (?)', array(Car_Parent::TYPE_TUNING, Car_Parent::TYPE_SPORT))
                    ->group(array('parent_id', 'type'))
            );

            foreach ($rows as $row) {
                $carId = (int)$row['parent_id'];
                $typeId = (int)$row['type'];
                if (!isset($carsTypeCounts[$carId])) {
                    $carsTypeCounts[$carId] = array();
                }
                $carsTypeCounts[$carId][$typeId] = (int)$row['count'];
            }
        }

        // lang names
        $carsLangName = array();
        if ($carIds) {
            $carLangRows = $this->getCarLanguageTable()->fetchAll(array(
                'car_id IN (?)' => $carIds,
                'language = ?'  => $language,
                'length(name) > 0'
            ));
            foreach ($carLangRows as $carLangRow) {
                $carsLangName[$carLangRow->car_id] = $carLangRow->name;
            }
        }

        // design projects
        $carsDesignProject = array();
        $designProjectTable = new Design_Projects();
        $db = $designProjectTable->getAdapter();
        if ($carIds) {
            $designProjectRows = $db->fetchAll(
                $db->select()
                    ->from($designProjectTable->info('name'), array('catname', 'brand_id'))
                    ->join('cars', 'design_projects.id = cars.design_project_id', null)
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.parent_id', 'car_id')
                    ->join('brands', 'design_projects.brand_id = brands.id', array('brand_name' => 'caption', 'brand_catname' => 'folder'))
                    ->where('car_parent_cache.car_id IN (?)', $carIds)
                    ->group('car_parent_cache.car_id')
            );
            foreach ($designProjectRows as $row) {
                $carsDesignProject[$row['car_id']] = array(
                    'brandName' => $row['brand_name'],
                    'url'       => $urlHelper->url(array(
                        'action'                 => 'design-project',
                        'brand_catname'          => $row['brand_catname'],
                        'design_project_catname' => $row['catname']
                    ), 'catalogue', true)
                );
            }
        }
        
        // new design projects
        $designCarsRows = $carParentAdapter->fetchAll(
            $carParentAdapter->select()
                ->from('brands', [
                    'brand_name'    => 'caption', 
                    'brand_catname' => 'folder'
                ])
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', [
                    'brand_car_catname' => 'catname'
                ])
                ->where('brands_cars.type = ?', Brands_Cars::TYPE_DESIGN)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', 'car_id')
                ->where('car_parent_cache.car_id IN (?)', $carIds)
                ->group('car_parent_cache.car_id')
        );
        foreach ($designCarsRows as $designCarsRow) {
            $carsDesignProject[$designCarsRow['car_id']] = array(
                'brandName' => $designCarsRow['brand_name'],
                'url'       => $urlHelper->url(array(
                    'controller'    => 'catalogue',
                    'action'        => 'brand-car',
                    'brand_catname' => $designCarsRow['brand_catname'],
                    'car_catname'   => $designCarsRow['brand_car_catname']
                ), 'catalogue', true)
            );
        }

        // total pictures
        $carsTotalPictures = $this->carsTotalPictures($carIds, $onlyExactlyPictures);

        $items = array();
        foreach ($cars as $car) {

            $totalPictures = isset($carsTotalPictures[$car->id]) ? $carsTotalPictures[$car->id] : null;

            $designProjectData = false;
            if (isset($carsDesignProject[$car->id])) {
                $designProjectData = $carsDesignProject[$car->id];
            }

            $categories = array();
            if (!$disableCategories) {
                $categories = isset($carsCategories[$car->id]) ? $carsCategories[$car->id] : array();
            }

            $pGroupId = null;
            $useLargeFormat = false;
            if ($perspectiveGroup) {
                $pGroupId = $perspectiveGroup;
            } else {
                $useLargeFormat = $totalPictures > 30 && !$disableLargePictures;
                $pGroupId = $useLargeFormat ? 5 : 4;
            }

            $g = $this->getPerspectiveGroupIds($pGroupId);

            $carOnlyChilds = isset($onlyChilds[$car->id]) ? $onlyChilds[$car->id] : null;

            $pictures = $this->getOrientedPictureList(
                $car, $g, $onlyExactlyPictures, $type, $picturesDateSort,
                $allowUpPictures, $language, $picHelper, $catalogue,
                $carOnlyChilds, $useLargeFormat, $pictureUrlCallback
            );

            if ($hideEmpty) {
                $hasPictures = false;
                foreach ($pictures as $picture) {
                    if ($picture) {
                        $hasPictures = true;
                        break;
                    }
                }

                if (!$hasPictures) {
                    continue;
                }
            }

            $hasHtml = (bool)$car->full_text_id;

            $specsLinks = array();
            if (!$disableSpecs) {
                if ($specificationsUrl) {
                    $url = $specificationsUrl($car);
                    if ($url) {
                        $specsLinks[] = array(
                            'name' => null,
                            'url'  => $url
                        );
                    }
                } else {
                    if ($hasSpecs[$car->id]) {
                        foreach ($catalogue->cataloguePaths($car) as $path) {
                            $specsLinks[] = array(
                                'name' => null,
                                'url'  => $urlHelper->url(array(
                                    'module'        => 'default',
                                    'controller'    => 'catalogue',
                                    'action'        => 'brand-car-specifications',
                                    'brand_catname' => $path['brand_catname'],
                                    'car_catname'   => $path['car_catname'],
                                    'path'          => $path['path']
                                ), 'catalogue', true)
                            );
                            break;
                        }
                    }
                }
            }

            $childsCount = isset($childsCounts[$car->id]) ? $childsCounts[$car->id] : 0;

            /*$spec = null;
            if ($car->spec_id) {
                $specRow = $this->getSpecTable()->find($car->spec_id)->current();
                if ($specRow) {
                    $spec = $specRow->short_name;
                }
            }*/

            $item = array(
                'id'               => $car->id,
                'row'              => $car,
                'name'             => $car->caption,
                'langName'         => isset($carsLangName[$car->id]) ? $carsLangName[$car->id] : null,
                'produced'         => $car->produced,
                'produced_exactly' => $car->produced_exactly,
                'designProject'    => $designProjectData,
                'totalPictures'    => $totalPictures,
                'categories'       => $categories,
                'pictures'         => $pictures,
                'hasHtml'          => $hasHtml,
                'hasChilds'        => $childsCount > 0,
                'childsCount'      => $childsCount,
                'specsLinks'       => $specsLinks,
                'largeFormat'      => $useLargeFormat,
            );

            if (!$disableTwins) {
                $item['twinsGroups'] = isset($carsTwinsGroups[$car->id]) ? $carsTwinsGroups[$car->id] : array();
            }

            if (count($item['pictures']) < $item['totalPictures']) {

                if ($allPicturesUrl) {

                    $item['allPicturesUrl'] = $allPicturesUrl($car);

                }
            }

            if (!$disableDetailsLink && ($hasHtml || $childsCount > 0)) {
                $url = null;

                if (is_callable($detailsUrl)) {

                    $url = $detailsUrl($car);

                } else {

                    if ($detailsUrl !== false) {

                        $cataloguePaths = $catalogue->cataloguePaths($car);

                        $url = null;
                        foreach ($cataloguePaths as $cPath) {
                            $url = $urlHelper->url(array(
                                'module'        => 'default',
                                'controller'    => 'catalogue',
                                'action'        => 'brand-car',
                                'brand_catname' => $cPath['brand_catname'],
                                'car_catname'   => $cPath['car_catname'],
                                'path'          => $cPath['path']
                            ), 'catalogue', true);
                            break;
                        }
                    }
                }

                if ($url) {
                    $item['details'] = array(
                        'url' => $url
                    );
                }
            }

            if (!$disableDescription) {
                $description = null;
                if ($car->text_id) {
                    $description = $textStorage->getText($car->text_id);
                }
                $item['description'] = $description;
            }

            if ($specEditor) {
                $item['specEditorUrl'] = $urlHelper->url(array(
                    'module'     => 'default',
                    'controller' => 'cars',
                    'action'     => 'car-specifications-editor',
                    'car_id'     => $car->id
                ), 'default', true);
            }

            if ($isCarModer) {
                $item['moderUrl'] = $urlHelper->url(array(
                    'module'     => 'moder',
                    'controller' => 'cars',
                    'action'     => 'car',
                    'car_id'     => $car->id
                ), 'default', true);
            }

            if ($typeUrl) {

                $tuningCount = isset($carsTypeCounts[$car->id][Car_Parent::TYPE_TUNING]) ? $carsTypeCounts[$car->id][Car_Parent::TYPE_TUNING] : 0;
                if ($tuningCount) {
                    $item['tuning'] = array(
                        'count' => $tuningCount,
                        'url'   => $typeUrl($car, Car_Parent::TYPE_TUNING)
                    );
                }

                $sportCount = isset($carsTypeCounts[$car->id][Car_Parent::TYPE_SPORT]) ? $carsTypeCounts[$car->id][Car_Parent::TYPE_SPORT] : 0;
                if ($sportCount) {
                    $item['sport'] = array(
                        'count' => $sportCount,
                        'url'   => $typeUrl($car, Car_Parent::TYPE_SPORT)
                    );
                }
            }

            if ($callback) {
                $callback($item);
            }

            $items[] = $item;
        }

        // collect all pictures
        $allPictures = array();
        $allFormatRequests = array();
        foreach ($items as $item) {
            foreach ($item['pictures'] as $picture) {
                if ($picture) {
                    $row = $picture['row'];
                    $allPictures[] = $row;
                    $allFormatRequests[$picture['format']][$row['id']] = $catalogue->getPictureFormatRequest($row);
                }
            }
        }


        // prefetch names
        $pictureNames = $pictureTable->getNameData($allPictures, array(
            'language' => $language
        ));

        // prefetch images
        $imagesInfo = array();
        foreach ($allFormatRequests as $format => $requests) {
            $imagesInfo[$format] = $imageStorage->getFormatedImages($requests, $format);
        }

        // populate prefetched
        foreach ($items as &$item) {
            foreach ($item['pictures'] as &$picture) {
                if ($picture) {
                    $id = $picture['row']['id'];
                    $format = $picture['format'];

                    $picture['name'] = isset($pictureNames[$id]) ? $pictureNames[$id] : null;
                    $picture['src'] = isset($imagesInfo[$format][$id]) ? $imagesInfo[$format][$id]->getSrc() : null;
                    unset($picture['row'], $picture['format']);
                }
            }
        }
        unset($item, $picture);

        return array(
            'specEditor'         => $specEditor,
            'isCarModer'         => $isCarModer,
            'disableDescription' => $disableDescription,
            'disableDetailsLink' => $disableDetailsLink,
            'disableTitle'       => $disableTitle,
            'items'              => $items,
        );
    }

    private function getPictureTable()
    {
        return $this->getActionController()->getHelper('Catalogue')->direct()->getPictureTable();
    }

    private function getPerspectiveGroupIds($pageId)
    {
        if (!isset($this->_perspectiveCache[$pageId])) {
            $perspectivesGroups = new Perspectives_Groups();
            $db = $perspectivesGroups->getAdapter();
            $this->_perspectiveCache[$pageId] = $db->fetchCol(
                $db->select()
                    ->from($perspectivesGroups->info('name'), 'id')
                    ->where('page_id = ?', $pageId)
                    ->order('position')
            );
        }

        return $this->_perspectiveCache[$pageId];
    }

    private function getPictureSelect($car, array $options)
    {
        $defaults = array(
            'onlyExactlyPictures' => false,
            'perspectiveGroup'    => false,
            'type'                => null,
            'exclude'             => array(),
            'dateSort'            => false,
            'onlyChilds'          => null
        );
        $options = array_merge($defaults, $options);

        $pictureTable = $this->getPictureTable();
        $db = $pictureTable->getAdapter();

        $select = $db->select()
            ->from(
                $pictureTable->info('name'),
                array(
                    'id', 'name', 'type', 'brand_id', 'engine_id', 'car_id', 'factory_id',
                    'perspective_id', 'image_id', 'crop_left', 'crop_top',
                    'crop_width', 'crop_height', 'width', 'height', 'identity'
                )
            )
            ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
            ->where('pictures.status IN (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
            ->limit(1);

        $order = array();

        if ($options['onlyExactlyPictures']) {

            $select->where('pictures.car_id = ?', $car->id);

        } else {

            $select
                ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                ->join('cars', 'pictures.car_id = cars.id', null)
                ->where('car_parent_cache.parent_id = ?', $car->id);

            $order[] = 'cars.is_concept asc';
            $order[] = 'car_parent_cache.sport asc';
            $order[] = 'car_parent_cache.tuning asc';

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
        }

        if ($options['perspectiveGroup']) {
            $select
                ->join(array('mp' => 'perspectives_groups_perspectives'), 'pictures.perspective_id = mp.perspective_id', null)
                ->where('mp.group_id = ?', $options['perspectiveGroup']);

            $order[] = 'mp.position';
        }

        if ($options['exclude']) {
            $select->where('pictures.id not in (?)', $options['exclude']);
        }

        if ($options['dateSort']) {
            $select->join(array('picture_car' => 'cars'), 'cars.id = picture_car.id', null);
            $order = array_merge($order, array('picture_car.begin_order_cache', 'picture_car.end_order_cache'));
        }
        $order = array_merge($order, array('pictures.width DESC', 'pictures.height DESC'));

        $select->order($order);

        if ($options['onlyChilds']) {
            $select
                ->join(
                    array('cpc_oc' => 'car_parent_cache'),
                    'cpc_oc.car_id = pictures.car_id',
                    null
                )
                ->where('cpc_oc.parent_id IN (?)', $options['onlyChilds']);
        }

        return $select;
    }

    private function getOrientedPictureList($car, array $perspectiveGroupIds,
            $onlyExactlyPictures, $type, $dateSort, $allowUpPictures, $language,
            $picHelper, $catalogue, $onlyChilds, $useLargeFormat, $urlCallback)
    {
        $pictures = array();
        $usedIds = array();

        $pictureTable = $this->getPictureTable();
        $db = $pictureTable->getAdapter();

        foreach ($perspectiveGroupIds as $groupId) {

            $select = $this->getPictureSelect($car, array(
                'onlyExactlyPictures' => $onlyExactlyPictures,
                'perspectiveGroup'    => $groupId,
                'type'                => $type,
                'exclude'             => $usedIds,
                'dateSort'            => $dateSort,
                'onlyChilds'          => $onlyChilds
            ));

            $picture = $db->fetchRow($select);

            if ($picture) {
                $pictures[] = $picture;
                $usedIds[] = (int)$picture['id'];
            } else {
                $pictures[] = null;
            }
        }

        $needMore = count($perspectiveGroupIds) - count($usedIds);

        if ($needMore > 0) {

            $select = $this->getPictureSelect($car, array(
                'onlyExactlyPictures' => $onlyExactlyPictures,
                'type'                => $type,
                'exclude'             => $usedIds,
                'dateSort'            => $dateSort,
                'onlyChilds'          => $onlyChilds
            ));

            $rows = $db->fetchAll(
                $select->limit($needMore)
            );
            $morePictures = array();
            foreach ($rows as $row) {
                $morePictures[] = $row;
            }

            foreach ($pictures as $key => $picture) {
                if (count($morePictures) <= 0) {
                    break;
                }
                if (!$picture) {
                    $pictures[$key] = array_shift($morePictures);
                }
            }
        }

        /*$nothingFound = true;
        foreach ($pictures as $picture) {
            if ($picture) {
                $nothingFound = false;
                break;
            }
        }*/

        $notEmptyPics = array();
        foreach ($pictures as $picture) {
            if ($picture) {
                $notEmptyPics[] = $picture;
            }
        }

        $result = array();
        foreach ($pictures as $idx => $picture) {
            if ($picture) {
                $pictureId = $picture['id'];

                $format = $useLargeFormat && $idx == 0 ? 'picture-thumb-medium' : 'picture-thumb';

                if ($urlCallback) {
                    $url = $urlCallback($car, $picture);
                } else {
                    $url = $picHelper->href($picture);
                }

                $result[] = array(
                    'format' => $format,
                    'row'    => $picture,
                    'url'    => $url,
                );
            } else {
                $result[] = false;
            }
        }

        return $result;
    }
}