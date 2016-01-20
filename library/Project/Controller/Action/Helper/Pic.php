<?php

class Project_Controller_Action_Helper_Pic extends Zend_Controller_Action_Helper_Abstract
{
    /**
     * @var Picture_View
     */
    protected $_pictureViewTable = null;

    protected $_moderVoteTable = null;

    /**
     * @return Pictures_Moder_Votes
     */
    protected function getModerVoteTable()
    {
        return $this->_moderVoteTable
            ? $this->_moderVoteTable
            : $this->_moderVoteTable = new Pictures_Moder_Votes();
    }

    /**
     * @return Picture_View
     */
    protected function getPictureViewTable()
    {
        return $this->_pictureViewTable
            ? $this->_pictureViewTable
            : $this->_pictureViewTable = new Picture_View();
    }

    public function href($row, array $options = array())
    {
        $defaults = array(
            'fallback' => true
        );
        $options = array_replace($defaults, $options);

        $urlHelper = $this->getActionController()->getHelper('Url');

        $brandTable = new Brands();

        $url = null;
        switch ($row['type']) {
            case Picture::LOGO_TYPE_ID:
                $brandRow = $brandTable->find($row['brand_id'])->current();
                if ($brandRow) {
                    $url = $urlHelper->url(array(
                        'module'        => 'default',
                        'controller'    => 'catalogue',
                        'action'        => 'logotypes-picture',
                        'brand_catname' => $brandRow->folder,
                        'picture_id'    => $row['identity'] ? $row['identity'] : $row['id']
                    ), 'catalogue', true);
                }
                break;

            case Picture::MIXED_TYPE_ID:
                $brandRow = $brandTable->find($row['brand_id'])->current();
                if ($brandRow) {
                    $url = $urlHelper->url(array(
                        'module'        => 'default',
                        'controller'    => 'catalogue',
                        'action'        => 'mixed-picture',
                        'brand_catname' => $brandRow->folder,
                        'picture_id'    => $row['identity'] ? $row['identity'] : $row['id']
                    ), 'catalogue', true);
                }
                break;

            case Picture::UNSORTED_TYPE_ID:
                $brandRow = $brandTable->find($row['brand_id'])->current();
                if ($brandRow) {
                    $url = $urlHelper->url(array(
                        'module'        => 'default',
                        'controller'    => 'catalogue',
                        'action'        => 'other-picture',
                        'brand_catname' => $brandRow->folder,
                        'picture_id'    => $row['identity'] ? $row['identity'] : $row['id']
                    ), 'catalogue', true);
                }
                break;

            case Picture::CAR_TYPE_ID:
                if ($row['car_id']) {
                    $carParentTable = new Car_Parent();
                    $paths = $carParentTable->getPaths($row['car_id'], array(
                        'breakOnFirst' => true
                    ));

                    if (count($paths) > 0) {
                        $path = $paths[0];

                        $url = $urlHelper->url(array(
                            'module'        => 'default',
                            'controller'    => 'catalogue',
                            'action'        => 'brand-car-picture',
                            'brand_catname' => $path['brand_catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path'],
                            'picture_id'    => $row['identity'] ? $row['identity'] : $row['id']
                        ), 'catalogue', true);
                    }
                }
                break;

            case Picture::ENGINE_TYPE_ID:
                if ($row['engine_id']) {
                    $engineTable = new Engines();

                    $parentId = $row['engine_id'];
                    $path = [];
                    do {
                        $engineRow = $engineTable->find($parentId)->current();
                        $path[] = $engineRow->id;

                        $brandRow = $brandTable->fetchRow(
                            $brandTable->select(true)
                                ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                                ->where('brand_engine.engine_id = ?', $engineRow->id)
                        );
                        $parentId = $engineRow->parent_id;
                    } while ($parentId && !$brandRow);

                    if ($brandRow && $path) {
                        $url = $urlHelper->url(array(
                            'module'        => 'default',
                            'controller'    => 'catalogue',
                            'action'        => 'engine-picture',
                            'brand_catname' => $brandRow['folder'],
                            'path'          => array_reverse($path),
                            'picture_id'    => $row['identity'] ? $row['identity'] : $row['id']
                        ), 'catalogue', true);
                    }
                }
                break;
        }

        if ($options['fallback'] && !$url) {
            $url = $this->url($row['id'], $row['identity']);
        }

        return $url;
    }

    public function url($id, $identity)
    {
        $urlHelper = $this->getActionController()->getHelper('Url');

        return $urlHelper->url(array(
            'picture_id' => $identity ? $identity : $id,
        ), 'picture', true);
    }

    public function listData($pictures, array $options = array())
    {
        $defaults = array(
            'width'            => null,
            'disableBehaviour' => false,
            'url'              => null
        );

        $options = array_replace($defaults, $options);

        $urlCallback = $options['url'];

        $colClass = '';
        $width = null;

        if ($options['width']) {
            $width = (int)$options['width'];
            if (!$colClass) {
                $colClass = 'col-lg-' . (12 / $width) . ' col-md-' . (12 / $width);
            }
        }

        $controller = $this->getActionController();
        $userHelper = $controller->getHelper('user');
        $urlHelper = $controller->getHelper('url');
        $isModer = $userHelper->inheritsRole('pictures-moder');
        $userId = null;
        if ($userHelper->logedIn()) {
            $user = $userHelper->get();
            $userId = $user ? $user->id : null;
        }

        $language = $controller->getHelper('language')->direct();

        $ids = array();

        if (is_array($pictures)) {

            $rows = array();
            foreach ($pictures as $picture) {
                $ids[] = $picture['id'];
                $rows[] = $picture->toArray();
            }

            // moder votes
            $moderVotes = array();
            if (count($ids)) {
                $moderVoteTable = $this->getModerVoteTable();
                $db = $moderVoteTable->getAdapter();

                $voteRows = $db->fetchAll(
                    $db->select()
                        ->from($moderVoteTable->info('name'), array(
                            'picture_id',
                            'vote'  => new Zend_Db_Expr('sum(if(vote, 1, -1))'),
                            'count' => 'count(1)'
                        ))
                        ->where('picture_id in (?)', $ids)
                        ->group('picture_id')
                );

                foreach ($voteRows as $row) {
                    $moderVotes[$row['picture_id']] = array(
                        'moder_votes'       => (int)$row['vote'],
                        'moder_votes_count' => (int)$row['count']
                    );
                }
            }

            // views
            $views = array();
            if (!$options['disableBehaviour']) {
                $views = $this->getPictureViewTable()->getValues($ids);
            }

            // messages
            $messages = array();
            if (!$options['disableBehaviour'] && count($ids)) {
                $ctTable = new Comment_Topic();
                $db = $ctTable->getAdapter();
                $messages = $db->fetchPairs(
                    $ctTable->select()
                        ->from($ctTable->info('name'), array('item_id', 'messages'))
                        ->where('item_id in (?)', $ids)
                        ->where('type_id = ?', Comment_Message::PICTURES_TYPE_ID)
                );
            }

            foreach ($rows as &$row) {
                $id = $row['id'];
                if (isset($moderVotes[$id])) {
                    $vote = $moderVotes[$id];
                    $row['moder_votes'] = $vote['moder_votes'];
                    $row['moder_votes_count'] = $vote['moder_votes_count'];
                } else {
                    $row['moder_votes'] = null;
                    $row['moder_votes_count'] = 0;
                }
                if (!$options['disableBehaviour']) {
                    if (isset($views[$id])) {
                        $row['views'] = $views[$id];
                    } else {
                        $row['views'] = 0;
                    }
                    if (isset($messages[$id])) {
                        $row['messages'] = $messages[$id];
                    } else {
                        $row['messages'] = 0;
                    }
                }
            }
            unset($row);

        } elseif ($pictures instanceof Zend_Db_Table_Select) {

            $table = $pictures->getTable();
            $db = $table->getAdapter();

            $select = clone $pictures;
            $bind = array();

            $select
                ->reset(Zend_Db_Select::COLUMNS)
                ->setIntegrityCheck(false)
                ->columns(array(
                    'pictures.id', 'pictures.identity', 'pictures.name',
                    'pictures.width', 'pictures.height',
                    'pictures.crop_left', 'pictures.crop_top', 'pictures.crop_width', 'pictures.crop_height',
                    'pictures.status', 'pictures.image_id',
                    'pictures.brand_id', 'pictures.car_id', 'pictures.engine_id',
                    'pictures.perspective_id', 'pictures.type', 'pictures.factory_id'
                ));

            $select
                ->group('pictures.id')
                ->joinLeft('pictures_moder_votes', 'pictures.id = pictures_moder_votes.picture_id', array(
                    'moder_votes'       => 'sum(if(pictures_moder_votes.vote, 1, -1))',
                    'moder_votes_count' => 'count(pictures_moder_votes.picture_id)'
                ));



            if (!$options['disableBehaviour']) {
                $select
                    ->joinLeft(array('pv' => 'picture_view'), 'pictures.id = pv.picture_id', 'views')
                    ->joinLeft(array('ct' => 'comment_topic'), 'ct.type_id = :type_id and ct.item_id = pictures.id', 'messages');

                $bind['type_id'] = Comment_Message::PICTURES_TYPE_ID;
            }

            $rows = $db->fetchAll($select, $bind);


            foreach ($rows as $idx => $picture) {
                $ids[] = (int)$picture['id'];
            }

        } else {
            throw new Exception("Unexpected type of pictures");
        }

        //print $select;

        // prefetch
        $requests = array();
        foreach ($rows as $idx => $picture) {
            $requests[$idx] = Pictures_Row::buildFormatRequest($picture);
        }

        // images
        $imageStorage = Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('imagestorage');

        $imagesInfo = $imageStorage->getFormatedImages($requests, 'picture-thumb');

        // names
        $pictureTable = new Picture();
        $names = $pictureTable->getNames($rows, array(
            'language' => $language
        ));

        // comments
        if (!$options['disableBehaviour']) {
            if ($userId) {
                $ctTable = new Comment_Topic();
                $newMessages = $ctTable->getNewMessages(
                    Comment_Message::PICTURES_TYPE_ID,
                    $ids,
                    $userId
                );
            }
        }

        $brandTable = new Brands();
        $carParentTable = new Car_Parent();

        $items = array();
        foreach ($rows as $idx => $row) {

            $id = (int)$row['id'];

            $name = isset($names[$id]) ? $names[$id] : null;

            if ($urlCallback) {
                $url = $urlCallback($row);
            } else {
                $url = $this->href($row);
            }

            $item = array(
                'id'        => $id,
                'type'      => $row['type'],
                'name'      => $name,
                'url'       => $url,
                'src'       => isset($imagesInfo[$idx]) ? $imagesInfo[$idx]->getSrc() : null,
                'moderVote' => $row['moder_votes_count'] > 0 ? $row['moder_votes'] : null,
            );

            if (!$options['disableBehaviour']) {
                $msgCount = $row['messages'];
                $newMsgCount = 0;
                if ($userId) {
                    $newMsgCount = isset($newMessages[$id]) ? $newMessages[$id] : $msgCount;
                }

                $item = array_replace($item, array(
                    'resolution'     => (int)$row['width'] . '×' . (int)$row['height'],
                    'cropped'        => Pictures_Row::checkCropParameters($row),
                    'cropResolution' => $row['crop_width'] . '×' . $row['crop_height'],
                    'status'         => $row['status'],
                    'views'          => (int)$row['views'],
                    'msgCount'       => $msgCount,
                    'newMsgCount'    => $newMsgCount,
                ));
            }



            $items[] = $item;
        }

        return array(
            'items'            => $items,
            'colClass'         => $colClass,
            'disableBehaviour' => $options['disableBehaviour'],
            'isModer'          => $isModer,
            'width'            => $width
        );
    }

    public function picPageData($picture, $picSelect, $brandIds = array())
    {
        $controller = $this->getActionController();
        $userHelper = $controller->getHelper('user');
        $catalogue = $controller->getHelper('catalogue')->getCatalogue();
        $urlHelper = $controller->getHelper('url');

        $isModer = $userHelper->direct()->inheritsRole('moder');

        $pictureTable = $catalogue->getPictureTable();
        $db = $pictureTable->getAdapter();

        $engine = null;
        $engineCars = array();
        $engineHasSpecs = false;
        $engineSpecsUrl = false;
        $factory = null;
        $factoryCars = array();
        $factoryCarsMore = false;
        $altNames2 = array();
        $currentLangName = null;
        $designProject = null;
        $categories = array();

        $car = null;
        $carDetailsUrl = null;

        $language = $controller->getHelper('language')->direct();

        switch ($picture->type) {
            case Picture::ENGINE_TYPE_ID:
                if ($engine = $picture->findParentEngines()) {

                    $brandIds = $db->fetchCol(
                        $db->select()
                            ->from('brand_engine', 'brand_id')
                            ->where('engine_id = ?', $engine->id)
                    );

                    $carIds = $engine->getRelatedCarGroupId();
                    if ($carIds) {
                        $carTable = $catalogue->getCarTable();

                        $carRows = $carTable->fetchAll(array(
                            'id in (?)' => $carIds
                        ), $catalogue->carsOrdering());

                        foreach ($carRows as $carRow) {
                            $cataloguePaths = $catalogue->cataloguePaths($carRow);

                            foreach ($cataloguePaths as $cPath) {
                                $engineCars[] = array(
                                    'name' => $carRow->getFullName($language),
                                    'url'  => $urlHelper->url(array(
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

                    $specService = new Application_Service_Specifications();
                    $engineHasSpecs = $specService->hasSpecs(3, $engine->id);

                    if ($engineHasSpecs) {

                        $cataloguePaths = $catalogue->engineCataloguePaths($engine, array(
                            'limit' => 1
                        ));

                        foreach ($cataloguePaths as $cataloguePath) {
                            $engineSpecsUrl = $urlHelper->url(array(
                                'module'        => 'default',
                                'controller'    => 'catalogue',
                                'action'        => 'engine-specs',
                                'brand_catname' => $cataloguePath['brand_catname'],
                                'path'          => $cataloguePath['path']
                            ), 'catalogue', true);
                        }
                    }

                }

                break;

            case Picture::LOGO_TYPE_ID:
            case Picture::MIXED_TYPE_ID:
            case Picture::UNSORTED_TYPE_ID:
                $brandIds = [$picture->brand_id];
                break;

            case Picture::FACTORY_TYPE_ID:
                if ($factory = $picture->findParentFactory()) {
                    $carIds = $factory->getRelatedCarGroupId();
                    if ($carIds) {
                        $carTable = $catalogue->getCarTable();

                        $carRows = $carTable->fetchAll(array(
                            'id in (?)' => $carIds
                        ), $catalogue->carsOrdering());

                        $limit = 10;

                        if (count($carRows) > $limit) {
                            $a = array();
                            foreach ($carRows as $carRow) {
                                $a[] = $carRow;
                            }
                            $carRows = array_slice($a, 0, $limit);
                            $factoryCarsMore = true;
                        }

                        foreach ($carRows as $carRow) {
                            $cataloguePaths = $catalogue->cataloguePaths($carRow);

                            foreach ($cataloguePaths as $cPath) {
                                $factoryCars[] = array(
                                    'name' => $carRow->getFullName($language),
                                    'url'  => $urlHelper->url(array(
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
                }
                break;
            case Picture::CAR_TYPE_ID:
                $car = $picture->findParentCars();
                if ($car) {

                    $brandIds = $db->fetchCol(
                        $db->select()
                            ->from('brands_cars', 'brand_id')
                            ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                            ->where('car_parent_cache.car_id = ?', $car->id)
                    );

                    // alt names
                    $altNames = array();

                    $carLangTable = new Car_Language();
                    $carLangRows = $carLangTable->fetchAll(array(
                        'car_id = ?' => $car->id
                    ));

                    $defaultName = $car->caption;
                    foreach ($carLangRows as $carLangRow) {
                        $name = $carLangRow->name;
                        if (!isset($altNames[$name])) {
                            $altNames[$carLangRow->name] = array();
                        }
                        $altNames[$name][] = $carLangRow->language;

                        if ($language == $carLangRow->language) {
                            $currentLangName = $name;
                        }
                    }

                    foreach ($altNames as $name => $codes) {
                        if (strcmp($name, $defaultName) != 0) {
                            $altNames2[$name] = $codes;
                        }
                    }

                    if ($currentLangName) {
                        unset($altNames2[$currentLangName]);
                    }

                    $designProjectTable = new Design_Projects();
                    $designProjectRow = $designProjectTable->fetchRow(
                        $designProjectTable->select(true)
                            ->join('cars', 'design_projects.id = cars.design_project_id', null)
                            ->join('car_parent_cache', 'cars.id = car_parent_cache.parent_id', null)
                            ->where('car_parent_cache.car_id = ?', $car->id)
                            ->limit(1)
                    );

                    if ($designProjectRow) {
                        $dbBrand = $designProjectRow->findParentBrands();
                        $designProject = array(
                            'url'   => $urlHelper->url(array(
                                'action'                 => 'design-project',
                                'brand_catname'          => $dbBrand->folder,
                                'design_project_catname' => $designProjectRow->catname
                            ), 'catalogue', true),
                            'brand' => $dbBrand->caption
                        );
                    }

                    $cdTable = new Category();
                    $cdlTable = new Category_Language();

                    $categoryRows = $cdTable->fetchAll(
                        $cdTable->select(true)
                            ->join('category_car', 'category.id = category_car.category_id', null)
                            ->join('car_parent_cache', 'category_car.car_id = car_parent_cache.parent_id', null)
                            ->where('car_parent_cache.car_id = ?', $car->id)
                    );

                    foreach ($categoryRows as $row) {
                        $lRow = $cdlTable->fetchRow(array(
                            'language = ?'    => $language,
                            'category_id = ?' => $row->id
                        ));
                        $categories[$row->id] = array(
                            'name' => $lRow ? $lRow->name : $row->name,
                            'url'  => $urlHelper->url(array(
                                'controller'       => 'category',
                                'action'           => 'category',
                                'category_catname' => $row['catname'],
                            ), 'category', true)
                        );
                    }

                    if ($car->html) {
                        foreach ($catalogue->cataloguePaths($car) as $path) {
                            $carDetailsUrl = $urlHelper->url(array(
                                'module'        => 'default',
                                'controller'    => 'catalogue',
                                'action'        => 'brand-car',
                                'brand_catname' => $path['brand_catname'],
                                'car_catname'   => $path['car_catname'],
                                'path'          => $path['path']
                            ), 'catalogue', true);
                            break;
                        }
                    }
                }
                break;
        }



        // ссылки на офсайты
        $ofLinks = array();
        $linksTable = new Links();
        if (count($brandIds)) {
            $links = $linksTable->fetchAll(
                $linksTable->select(true)
                    ->where('brandId in (?)', $brandIds)
                    ->where('type = ?', 'official')
            );
            foreach ($links as $link) {
                $ofLinks[$link->id] = $link;
            }
        }

        $replacePicture = null;
        if ($picture->replace_picture_id) {
            $replacePictureRow = $pictureTable->find($picture->replace_picture_id)->current();

            $picHelper = $controller->getHelper('pic');
            $replacePicture = $picHelper->href($replacePictureRow->toArray());

            if ($replacePictureRow->status == Picture::STATUS_REMOVING) {
                if (!$userHelper->direct()->inheritsRole('moder')) {
                    $replacePicture = null;
                }
            }
        }

        $moderLinks = array();
        if ($isModer) {
            $links = array();
            $links[$urlHelper->url(array(
                'module'     => 'moder',
                'controller' => 'pictures',
                'action'     => 'picture',
                'picture_id' => $picture->id
            ), 'default', true)] = 'Управление изображением №'.$picture->id;

            switch ($picture->type) {
                case Picture::CAR_TYPE_ID:
                    if ($car) {
                        $url = $urlHelper->url(array(
                            'module'     => 'moder',
                            'controller' => 'cars',
                            'action'     => 'car',
                            'car_id'     => $car->id
                        ), 'default', true);
                        $links[$url] = 'Управление автомобилем ' . $car->getFullName();

                        $brandTable = new Brands();

                        $brandRows = $brandTable->fetchAll(
                            $brandTable->select(true)
                                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                                ->where('car_parent_cache.car_id = ?', $car->id)
                                ->group('brands.id')
                        );

                        foreach ($brandRows as $brand) {
                            $url = $urlHelper->url(array(
                                'module'     => 'moder',
                                'controller' => 'brands',
                                'action'     => 'brand',
                                'brand_id'   => $brand->id
                            ), 'default', true);
                            $links[$url] = 'Управление брендом ' . $brand->caption;
                        }

                        if ($dp = $car->findParentDesign_Projects()) {
                            if ($brand = $dp->findParentBrands()) {
                                $url = $urlHelper->url(array(
                                    'module'     => 'moder',
                                    'controller' => 'brands',
                                    'action'     => 'brand',
                                    'brand_id'   => $brand->id
                                ), 'default', true);
                                $links[$url] = 'Управление брендом ' . $brand->caption;
                            }
                        }
                    }

                    break;

                case Picture::ENGINE_TYPE_ID:
                    if ($engine = $picture->findParentEngines()) {
                        $url = $urlHelper->url(array(
                            'module'     => 'moder',
                            'controller' => 'engines',
                            'action'     => 'engine',
                            'engine_id'  => $engine->id
                        ), 'default', true);
                        $links[$url] = 'Управление двигателем ' . $engine->caption;
                    }
                    break;

                case Picture::FACTORY_TYPE_ID:
                    if ($factory = $picture->findParentFactory()) {
                        $links[$urlHelper->url(array(
                            'module'     => 'moder',
                            'controller' => 'factory',
                            'action'     => 'factory',
                            'factory_id' => $factory->id
                        ), 'default', true)] = 'Управление заводом ' . $factory->name;
                    }
                    break;

                case Picture::MIXED_TYPE_ID:
                case Picture::LOGO_TYPE_ID:
                case Picture::UNSORTED_TYPE_ID:
                    if ($brand = $picture->findParentBrands()) {
                        $url = $urlHelper->url(array(
                            'module'     => 'moder',
                            'controller' => 'brands',
                            'action'     => 'brand',
                            'brand_id'   => $brand->id
                        ), 'default', true);
                        $links[$url] = 'Управление брендом ' . $brand->caption;
                    }
                    break;
            }

            $moderLinks = $links;
        }

        $userTable = new Users();

        $moderVotes = array();
        foreach ($picture->findPictures_Moder_Votes() as $moderVote) {
            $moderVotes[] = array(
                'vote'   => $moderVote->vote,
                'reason' => $moderVote->reason,
                'user'   => $userTable->find($moderVote->user_id)->current()
            );
        }

        $imageStorage = $controller->getInvokeArg('bootstrap')
            ->getResource('imagestorage');

        $image = $imageStorage->getImage($picture->image_id);
        $sourceUrl = $image ? $image->getSrc() : null;

        $preview = $imageStorage->getFormatedImage($picture->getFormatRequest(), 'picture-medium');
        $previewUrl = $preview ? $preview->getSrc() : null;


        $paginator = false;
        $pageNumbers = false;

        if ($picSelect) {

            $paginator = Zend_Paginator::factory($picSelect);

            $total = $paginator->getTotalItemCount();

            if ($total < 500) {

                $db = $pictureTable->getAdapter();

                $paginatorPictures = $db->fetchAll(
                    $db->select()
                        ->from(array('_pic' => new Zend_Db_Expr('('.$picSelect->assemble() .')')), array('id', 'identity'))
                );

                //$paginatorPictures = $pictureTable->fetchAll($picSelect);

                $pageNumber = 0;
                foreach ($paginatorPictures as $n => $p) {
                    if ($p['id'] == $picture->id) {
                        $pageNumber = $n+1;
                        break;
                    }
                }

                $paginator
                    ->setItemCountPerPage(1)
                    ->setPageRange(15)
                    ->setCurrentPageNumber($pageNumber);

                $pages = $paginator->getPages();

                $pageNumbers = $pages->pagesInRange;
                if (isset($pages->previous)) {
                    $pageNumbers[] = $pages->previous;
                }
                if (isset($pages->next)) {
                    $pageNumbers[] = $pages->next;
                }

                $pageNumbers = array_unique($pageNumbers);
                $pageNumbers = array_combine($pageNumbers, $pageNumbers);

                foreach($pageNumbers as $page => &$val) {
                    $pic = $paginatorPictures[$page - 1];
                    $val = $urlHelper->url(array(
                        'picture_id' => $pic['identity'] ? $pic['identity'] : $pic['id']
                    ));
                }
                unset($val);

            } else {
                $paginator = false;
            }
        }

        $name = $picture->getCaption(array(
            'language' => $language
        ));

        $mTable = new Modification();
        $mRows = $mTable->fetchAll(
            $mTable->select(true)
                ->join('modification_picture', 'modification.id = modification_picture.modification_id', null)
                ->where('modification_picture.picture_id = ?', $picture['id'])
                ->order('modification.name')
        );

        $modifications = [];
        foreach ($mRows as $mRow) {

            $url = null;
            $carTable = new Cars();
            $carRow = $carTable->find($mRow->car_id)->current();
            if ($carRow) {
                $carParentTable = new Car_Parent();
                $paths = $carParentTable->getPaths($carRow->id, array(
                    'breakOnFirst' => true
                ));
                if (count($paths) > 0) {
                    $path = $paths[0];

                    $url = $urlHelper->url(array(
                        'module'        => 'default',
                        'controller'    => 'catalogue',
                        'action'        => 'brand-car-pictures',
                        'brand_catname' => $path['brand_catname'],
                        'car_catname'   => $path['car_catname'],
                        'path'          => $path['path'],
                        'mod'           => $mRow->id
                    ), 'catalogue', true);
                }
            }

            $modifications[] = array(
                'name' => $mRow->name,
                'url'  => $url
            );
        }

        $data = array(
            'id'                => $picture['id'],
            'identity'          => $picture['identity'],
            'name'              => $name,
            'picture'           => $picture,
            'owner'             => $picture->findParentUsersByOwner(),
            'addDate'           => $picture->getDate('add_date'),
            'ofLinks'           => $ofLinks,
            'moderVotes'        => $moderVotes,
            'sourceUrl'         => $sourceUrl,
            'previewUrl'        => $previewUrl,
            'replacePicture'    => $replacePicture,
            'gallery'           => array(
                'current' => $picture->id
            ),
            'paginator'         => $paginator,
            'paginatorPictures' => $pageNumbers,
            'engine'            => $engine,
            'engineCars'        => $engineCars,
            'engineHasSpecs'    => $engineHasSpecs,
            'engineSpecsUrl'    => $engineSpecsUrl,
            'factory'           => $factory,
            'factoryCars'       => $factoryCars,
            'factoryCarsMore'   => $factoryCarsMore,
            'moderLinks'        => $moderLinks,
            'altNames'          => $altNames2,
            'langName'          => $currentLangName,
            'designProject'     => $designProject,
            'categories'        => $categories,
            'carDetailsUrl'     => $carDetailsUrl,
            'carHtml'           => $car ? $car->html : null,
            'carDescription'    => $car ? $car->description : null,
            'modifications'     => $modifications
        );

        // Обвновляем количество просмотров
        $views = new Picture_View();
        $views->inc($picture);

        return $data;
    }

    public function gallery(Zend_Db_Table_Select $picSelect)
    {
        $galleryStatuses = array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW);

        $gallery = array();

        $controller = $this->getActionController();
        $userHelper = $controller->getHelper('user');
        $catalogue = $controller->getHelper('catalogue')->getCatalogue();
        $urlHelper = $controller->getHelper('Url');

        $view = $controller->view;

        $language = $controller->getHelper('language')->direct();

        $select = clone $picSelect;

        $select
            ->reset(Zend_Db_Select::COLUMNS)
            ->setIntegrityCheck(false)
            ->columns(array(
                'pictures.id', 'pictures.identity', 'pictures.name',
                'pictures.width', 'pictures.height',
                'pictures.crop_left', 'pictures.crop_top', 'pictures.crop_width', 'pictures.crop_height',
                'pictures.image_id', 'pictures.filesize',
                'pictures.brand_id', 'pictures.car_id', 'pictures.engine_id',
                'pictures.perspective_id', 'pictures.type', 'pictures.factory_id'
            ))
            ->joinLeft(array('ct' => 'comment_topic'), 'ct.type_id = :type_id and ct.item_id = pictures.id', 'messages');

        $rows = $select->getAdapter()->fetchAll($select, array(
            'type_id' => Comment_Message::PICTURES_TYPE_ID
        ));



        // prefetch
        $fullRequests = array();
        $cropRequests = array();
        $imageIds = array();
        foreach ($rows as $idx => $picture) {
            $request = Pictures_Row::buildFormatRequest($picture);
            $fullRequests[$idx] = $request;
            if (Pictures_Row::checkCropParameters($picture)) {
                $cropRequests[$idx] = $request;
            }
            $ids[] = (int)$picture['id'];
            $imageIds[] = (int)$picture['image_id'];
        }

        // images
        $imageStorage = $controller->getInvokeArg('bootstrap')
            ->getResource('imagestorage');
        $images = $imageStorage->getImages($imageIds);
        $fullImagesInfo = $imageStorage->getFormatedImages($fullRequests, 'picture-gallery-full');
        $cropImagesInfo = $imageStorage->getFormatedImages($cropRequests, 'picture-gallery');


        // names
        $pictureTable = new Picture();
        $names = $pictureTable->getNames($rows, array(
            'language' => $language
        ));

        // comments
        $userId = null;
        if ($userHelper->direct()->logedIn()) {
            $userId = $userHelper->direct()->get()->id;
        }

        if ($userId) {
            $ctTable = new Comment_Topic();
            $newMessages = $ctTable->getNewMessages(
                Comment_Message::PICTURES_TYPE_ID,
                $ids,
                $userId
            );
        }


        foreach ($rows as $idx => $row) {

            $imageId = (int)$row['image_id'];

            if ($imageId) {

                $id = (int)$row['id'];

                $image = isset($images[$imageId]) ? $images[$imageId] : null;
                if ($image) {
                    $sUrl = $image->getSrc();

                    if (Pictures_Row::checkCropParameters($row)) {
                        $crop = isset($cropImagesInfo[$idx]) ? $cropImagesInfo[$idx]->toArray() : null;

                        $crop['crop'] = array(
                            'left'   => $row['crop_left'] / $image->getWidth(),
                            'top'    => $row['crop_top'] / $image->getHeight(),
                            'width'  => $row['crop_width'] / $image->getWidth(),
                            'height' => $row['crop_height'] / $image->getHeight(),
                        );

                    } else {
                        $crop = null;
                    }

                    $full = isset($fullImagesInfo[$idx]) ? $fullImagesInfo[$idx]->toArray() : null;

                    $msgCount = $row['messages'];
                    $newMsgCount = 0;
                    if ($userId) {
                        $newMsgCount = isset($newMessages[$id]) ? $newMessages[$id] : $msgCount;
                    }

                    $name = isset($names[$id]) ? $names[$id] : null;

                    $url = $urlHelper->url(array(
                        'picture_id' => $row['identity'] ? $row['identity'] : $id,
                        'gallery'    => null
                    ));

                    $gallery[] = array(
                        'id'          => $id,
                        'url'         => $url,
                        'sourceUrl'   => $sUrl,
                        'crop'        => $crop,
                        'full'        => $full,
                        'messages'    => $msgCount,
                        'newMessages' => $newMsgCount,
                        'name'        => $name,
                        'filesize'    => $view->fileSize($row['filesize'])
                    );
                }
            }
        }

        return $gallery;
    }

    public function gallery2(Zend_Db_Table_Select $picSelect, array $options = array())
    {
        $defaults = array(
            'page'      => 1,
            'pictureId' => null,
            'route'     => null,
            'urlParams' => []
        );
        $options = array_replace($defaults, $options);

        $itemsPerPage = 10;

        $galleryStatuses = array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW);

        $gallery = array();

        $controller = $this->getActionController();
        $userHelper = $controller->getHelper('user');
        $catalogue = $controller->getHelper('catalogue')->getCatalogue();
        $urlHelper = $controller->getHelper('Url');

        $view = $controller->view;

        $language = $controller->getHelper('language')->direct();

        if ($options['pictureId']) {
            // look for page of that picture
            $select = clone $picSelect;

            $select
                ->setIntegrityCheck(false)
                ->reset(Zend_Db_Select::COLUMNS)
                ->columns(array(
                    'pictures.id'
                ));

            $col = $select->getAdapter()->fetchCol($select);
            foreach ($col as $index => $id) {
                if ($id == $options['pictureId']) {
                    $options['page'] = ceil(($index+1) / $itemsPerPage);
                    break;
                }
            }
        }

        $select = clone $picSelect;

        $select
            ->setIntegrityCheck(false)
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns(array(
                'pictures.id', 'pictures.identity', 'pictures.name',
                'pictures.width', 'pictures.height',
                'pictures.crop_left', 'pictures.crop_top', 'pictures.crop_width', 'pictures.crop_height',
                'pictures.image_id', 'pictures.filesize',
                'pictures.brand_id', 'pictures.car_id', 'pictures.engine_id',
                'pictures.perspective_id', 'pictures.type', 'pictures.factory_id'
            ))
            ->joinLeft(
                array('ct' => 'comment_topic'),
                'ct.type_id = :type_id and ct.item_id = pictures.id',
                'messages'
            )
            ->bind(array(
                'type_id' => Comment_Message::PICTURES_TYPE_ID
            ));

        $count = Zend_Paginator::factory($select)->getTotalItemCount();



        $paginator = Zend_Paginator::factory($count)
            ->setItemCountPerPage($itemsPerPage)
            ->setCurrentPageNumber($options['page']);

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $rows = $select->getAdapter()->fetchAll($select);



        // prefetch
        $ids = [];
        $fullRequests = array();
        $cropRequests = array();
        $imageIds = array();
        foreach ($rows as $idx => $picture) {
            $request = Pictures_Row::buildFormatRequest($picture);
            $fullRequests[$idx] = $request;
            if (Pictures_Row::checkCropParameters($picture)) {
                $cropRequests[$idx] = $request;
            }
            $ids[] = (int)$picture['id'];
            $imageIds[] = (int)$picture['image_id'];
        }

        // images
        $imageStorage = $controller->getInvokeArg('bootstrap')
            ->getResource('imagestorage');
        $images = $imageStorage->getImages($imageIds);
        $fullImagesInfo = $imageStorage->getFormatedImages($fullRequests, 'picture-gallery-full');
        $cropImagesInfo = $imageStorage->getFormatedImages($cropRequests, 'picture-gallery');


        // names
        $pictureTable = new Picture();
        $names = $pictureTable->getNames($rows, array(
            'language' => $language
        ));

        // comments
        $userId = null;
        if ($userHelper->direct()->logedIn()) {
            $userId = $userHelper->direct()->get()->id;
        }

        if ($userId) {
            $ctTable = new Comment_Topic();
            $newMessages = $ctTable->getNewMessages(
                Comment_Message::PICTURES_TYPE_ID,
                $ids,
                $userId
            );
        }

        $route = $options['route'] ? $options['route'] : null;


        foreach ($rows as $idx => $row) {

            $imageId = (int)$row['image_id'];

            if ($imageId) {

                $id = (int)$row['id'];

                $image = isset($images[$imageId]) ? $images[$imageId] : null;
                if ($image) {
                    $sUrl = $image->getSrc();

                    if (Pictures_Row::checkCropParameters($row)) {
                        $crop = isset($cropImagesInfo[$idx]) ? $cropImagesInfo[$idx]->toArray() : null;

                        $crop['crop'] = array(
                            'left'   => $row['crop_left'] / $image->getWidth(),
                            'top'    => $row['crop_top'] / $image->getHeight(),
                            'width'  => $row['crop_width'] / $image->getWidth(),
                            'height' => $row['crop_height'] / $image->getHeight(),
                        );

                    } else {
                        $crop = null;
                    }

                    $full = isset($fullImagesInfo[$idx]) ? $fullImagesInfo[$idx]->toArray() : null;

                    $msgCount = $row['messages'];
                    $newMsgCount = 0;
                    if ($userId) {
                        $newMsgCount = isset($newMessages[$id]) ? $newMessages[$id] : $msgCount;
                    }

                    $name = isset($names[$id]) ? $names[$id] : null;

                    $url = $urlHelper->url(array_replace($options['urlParams'], array(
                        'picture_id' => $row['identity'] ? $row['identity'] : $id,
                        'gallery'    => null,
                    )), $route);

                    $gallery[] = array(
                        'id'          => $id,
                        'url'         => $url,
                        'sourceUrl'   => $sUrl,
                        'crop'        => $crop,
                        'full'        => $full,
                        'messages'    => $msgCount,
                        'newMessages' => $newMsgCount,
                        'name'        => $name,
                        'filesize'    => $view->fileSize($row['filesize'])
                    );
                }
            }
        }

        return array(
            'page'  => $paginator->getCurrentPageNumber(),
            'pages' => $paginator->count(),
            'count' => $paginator->getTotalItemCount(),
            'items' => $gallery
        );
    }
}