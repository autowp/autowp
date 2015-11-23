<?php
class TwinsController extends Zend_Controller_Action
{
    const GROUPS_PER_PAGE = 20;

    /**
     * @var Twins
     */
    private $_twins;

    /**
     * @return Twins
     */
    protected function _getTwins()
    {
        return $this->_twins
            ? $this->_twins
            : $this->_twins = new Twins();
    }

    private function _loadBrands(array $selectedIds)
    {
        $cache = $this->getInvokeArg('bootstrap')
            ->getResource('cachemanager')->getCache('long');

        $language = $this->_helper->language();

        $key = 'TWINS_SIDEBAR_4_' . $language;

        if (!($arr = $cache->load($key))) {

            $arr = $this->_getTwins()->getBrands(array(
                'language' => $language
            ));

            foreach ($arr as &$brand) {
                $brand['url'] = $this->_helper->url->url(array(
                    'action'        => 'brand',
                    'brand_catname' => $brand['folder']
                ), 'twins', true);
            }
            unset($brand);

            $cache->save($arr, null, array(), 1800);
        }

        foreach ($arr as &$brand) {
            $brand['selected'] = in_array($brand['id'], $selectedIds);
        }

        $this->view->brandList = $arr;
        $this->getResponse()->insert('sidebar', $this->view->render('twins/sidebar.phtml'));
    }

    public function specificationsAction()
    {
        $group = $this->_getTwins()->getGroup($this->_getParam('twins_group_id'));
        if (!$group) {
            return $this->_forward('notfound', 'error');
        }

        $service = new Application_Service_Specifications();
        $specs = $service->specifications($this->_getTwins()->getGroupCars($group['id']), array(
            'language' => 'en'
        ));

        $this->view->assign(array(
            'group' => $group,
            'specs' => $specs,
        ));
    }

    public function picturesAction()
    {
        $twins = $this->_getTwins();

        $group = $twins->getGroup($this->_getParam('twins_group_id'));
        if (!$group) {
            return $this->_forward('notfound', 'error');
        }

        $select = $twins->getGroupPicturesSelect($group['id'], array(
            'ordering' => $this->_helper->catalogue()->picturesOrdering()
        ));


        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage($this->_helper->catalogue()->getPicturesPerPage())
            ->setCurrentPageNumber($this->_getParam('page'));

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $picturesData = $this->_helper->pic->listData($select, array(
            'width' => 4,
            'url'   => function($row) use ($group) {
                return $this->_helper->url->url(array(
                    'controller'     => 'twins',
                    'action'         => 'picture',
                    'twins_group_id' => $group['id'],
                    'picture_id'     => $row['identity'] ? $row['identity'] : $row['id']
                ));
            }
        ));

        $this->view->assign(array(
            'group'        => $group,
            'paginator'    => $paginator,
            'picturesData' => $picturesData
        ));

        $this->_loadBrands($twins->getGroupBrandIds($group['id']));
    }

    public function groupAction()
    {
        $twins = $this->_getTwins();

        $group = $twins->getGroup($this->_getParam('twins_group_id'));
        if (!$group) {
            return $this->_forward('notfound', 'error');
        }

        $carList = $twins->getGroupCars($group['id']);

        $hasSpecs = false;

        $specService = new Application_Service_Specifications();

        foreach ($carList as $car) {
            $hasSpecs = $hasSpecs || $specService->hasSpecs(1, $car->id);
        }

        $picturesCount = $twins->getGroupPicturesCount($group['id']);

        $this->view->assign(array(
            'group'              => $group,
            'cars'               => $this->_helper->car->listData($carList, array(
                'disableTwins'         => true,
                'disableLargePictures' => true,
                'disableSpecs'         => true,
                'pictureUrl'           => function($car, $picture) use ($group) {
                    return $this->_helper->url->url(array(
                        'controller'     => 'twins',
                        'action'         => 'picture',
                        'twins_group_id' => $group['id'],
                        'picture_id'     => $picture['identity'] ? $picture['identity'] : $picture['id']
                    ));
                }
            )),
            'picturesCount'      => $picturesCount,
            'hasSpecs'           => $hasSpecs,
            'specsUrl'           => $this->_helper->url->url(array(
                'twins_group_id' => $group['id'],
                'action'         => 'specifications'
            ), 'twins', true),
            'picturesUrl'        => $this->_helper->url->url(array(
                'twins_group_id' => $group['id'],
                'action'         => 'pictures'
            ), 'twins', true),
        ));

        $this->_loadBrands($this->_getTwins()->getGroupBrandIds($group['id']));
    }

    protected function _prepareList($list)
    {
        $ctTable = new Comment_Topic();
        $pictureTable = new Picture();

        $imageStorage = $this->getInvokeArg('bootstrap')->getResource('imagestorage');

        $language = $this->_helper->language();

        $specService = new Application_Service_Specifications();

        $ids = array();
        foreach ($list as $group) {
            $ids[] = $group->id;
        }

        $picturesCounts = $this->_getTwins()->getGroupPicturesCount($ids);

        $commentsStats = $ctTable->getTopicStat(
            Comment_Message::TWINS_TYPE_ID,
            $ids
        );

        $hasSpecs = $specService->twinsGroupsHasSpecs($ids);

        $carLists = array();
        if (count($ids)) {

            $carTable = new Cars();

            $db = $carTable->getAdapter();

            $langJoinExpr = 'cars.id = car_language.car_id and ' .
                $db->quoteInto('car_language.language = ?', $language);

            $rows = $db->fetchAll(
                $db->select()
                    ->from('cars', array(
                        'cars.id',
                        'name' => 'if(length(car_language.name), car_language.name, cars.caption)',
                        'cars.body', 'cars.begin_model_year', 'cars.end_model_year',
                        'cars.begin_year', 'cars.end_year', 'cars.today',
                        'spec' => 'spec.short_name'
                    ))
                    ->join('twins_groups_cars', 'cars.id = twins_groups_cars.car_id', 'twins_group_id')
                    ->joinLeft('car_language', $langJoinExpr, null)
                    ->joinLeft('spec', 'cars.spec_id = spec.id', null)
                    ->where('twins_groups_cars.twins_group_id in (?)', $ids)
                    ->order('name')
            );
            foreach ($rows as $row) {
                $carLists[$row['twins_group_id']][] = $row;
            }
        }

        $groups = array();
        $requests = array();
        foreach ($list as $group) {

            $carList = isset($carLists[$group->id]) ? $carLists[$group->id] : array();

            $picturesShown = 0;
            $cars = array();

            foreach ($carList as $car) {
                $pictureRow = $pictureTable->fetchRow(
                    $pictureTable->select(true)
                        ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                        ->where('car_parent_cache.parent_id = ?', (int)$car['id'])
                        ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                        ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
                        ->order(array(
                            new Zend_Db_Expr('pictures.perspective_id=7 DESC'),
                            new Zend_Db_Expr('pictures.perspective_id=8 DESC')
                        ))
                        ->limit(1)
                );

                $picture = null;
                if ($pictureRow) {
                    $picturesShown++;

                    $key = 'g' . $group->id . 'p' . $pictureRow->id;

                    $request = $pictureRow->getFormatRequest();
                    $requests[$key] = $request;

                    $url = $this->_helper->url->url(array(
                        'controller'     => 'twins',
                        'action'         => 'picture',
                        'twins_group_id' => $group['id'],
                        'picture_id'     => $pictureRow['identity'] ? $pictureRow['identity'] : $pictureRow['id']
                    ));

                    $picture = array(
                        'key' => $key,
                        'url' => $url,
                        'src' => null
                    );
                }

                $name = Cars_Row::buildFullName(array(
                    'begin_model_year' => $car['begin_model_year'],
                    'end_model_year'   => $car['end_model_year'],
                    'spec'             => $car['spec'],
                    'body'             => $car['body'],
                    'name'             => $car['name'],
                    'begin_year'       => $car['begin_year'],
                    'end_year'         => $car['end_year'],
                    'today'            => $car['today']
                ));

                $cars[] = array(
                    'name'    => $name,
                    'picture' => $picture
                );
            }

            $commentsStat = isset($commentsStats[$group->id]) ? $commentsStats[$group->id] : null;
            $msgCount = $commentsStat ? $commentsStat['messages'] : 0;

            $picturesCount = isset($picturesCounts[$group->id]) ? $picturesCounts[$group->id] : null;

            $groups[] = array(
                'name'          => $group->name,
                'cars'          => $cars,
                'picturesShown' => $picturesShown,
                'picturesCount' => $picturesCount,
                'hasSpecs'      => isset($hasSpecs[$group->id]) && $hasSpecs[$group->id],
                'msgCount'      => $msgCount,
                'detailsUrl'    => $this->_helper->url->url(array(
                    'action'         => 'group',
                    'twins_group_id' => $group->id
                ), 'twins', true),
                'specsUrl'      => $this->_helper->url->url(array(
                    'twins_group_id' => $group->id,
                    'action'         => 'specifications'
                ), 'twins', true),
                'picturesUrl'   => $this->_helper->url->url(array(
                    'twins_group_id' => $group->id,
                    'action'         => 'pictures'
                ), 'twins', true),
                'moderUrl'      => $this->_helper->url->url(array(
                    'module'         => 'moder',
                    'controller'     => 'twins',
                    'action'         => 'twins-group',
                    'twins_group_id' => $group->id
                ), 'default', true)
            );
        }


        // fetch images from storage
        $imagesInfo = $imageStorage->getFormatedImages($requests, 'picture-thumb');

        foreach ($groups as &$group) {
            foreach ($group['cars'] as &$car) {
                if ($car['picture']) {
                    $key = $car['picture']['key'];
                    if (isset($imagesInfo[$key])) {
                        $car['picture']['src'] = $imagesInfo[$key]->getSrc();
                    }
                }
            }
            unset($car);
        }
        unset($group);

        return $groups;
    }

    public function brandAction()
    {
        $brand = $this->_helper->catalogue()->getBrandTable()->findRowByCatname($this->_getParam('brand_catname'));

        if (!$brand) {
            return $this->_forward('notfound', 'error');
        }

        $canEdit = $this->_helper->user()->isAllowed('twins', 'edit');

        $paginator = $this->_getTwins()->getGroupsPaginator(array(
                'brandId' => $brand->id
            ))
            ->setItemCountPerPage(self::GROUPS_PER_PAGE)
            ->setCurrentPageNumber($this->_getParam('page'));

        $groups = $this->_prepareList($paginator->getCurrentItems());

        $this->view->assign(array(
            'groups'    => $groups,
            'paginator' => $paginator,
            'brand'     => $brand,
            'canEdit'   => $canEdit
        ));

        $this->_loadBrands(array($brand->id));
    }

    public function indexAction()
    {
        $canEdit = $this->_helper->user()->isAllowed('twins', 'edit');

        $paginator = $this->_getTwins()->getGroupsPaginator()
            ->setItemCountPerPage(self::GROUPS_PER_PAGE)
            ->setCurrentPageNumber($this->_getParam('page'));

        $groups = $this->_prepareList($paginator->getCurrentItems());

        $this->view->assign(array(
            'groups'    => $groups,
            'paginator' => $paginator,
            'canEdit'   => $canEdit
        ));

        $this->_loadBrands(array());
    }

    private function _pictureAction($callback)
    {
        $twins = $this->_getTwins();

        $group = $twins->getGroup($this->_getParam('twins_group_id'));
        if (!$group) {
            return $this->_forward('notfound', 'error');
        }

        $pictureId = (string)$this->_getParam('picture_id');

        $select = $twins->getGroupPicturesSelect($group['id'])
            ->where('pictures.id = ?', $pictureId)
            ->where('pictures.identity IS NULL');

        $picture = $select->getTable()->fetchRow($select);

        if (!$picture) {
            $select = $twins->getGroupPicturesSelect($group['id'])
                ->where('pictures.identity = ?', $pictureId);

            $picture = $select->getTable()->fetchRow($select);
        }

        if (!$picture) {
            return $this->_forward('notfound', 'error');
        }

        $callback($group, $picture);
    }

    public function pictureAction()
    {
        $this->_pictureAction(function($group, $picture) {

            $twins = $this->_getTwins();

            $this->_loadBrands($twins->getGroupBrandIds($group['id']));

            $select = $twins->getGroupPicturesSelect($group['id'], array(
                'ordering' => $this->_helper->catalogue()->picturesOrdering()
            ));

            $data = $this->_helper->pic->picPageData($picture, $select, array());

            $this->view->assign($data);
            $this->view->assign(array(
                'group'      => $group,
                'gallery2'   => true,
                'galleryUrl' => $this->_helper->url->url(array(
                    'action' => 'picture-gallery'
                ))
            ));
        });
    }

    public function pictureGalleryAction()
    {
        $this->_pictureAction(function($group, $picture) {

            $select = $this->_getTwins()->getGroupPicturesSelect($group['id'], array(
                'ordering' => $this->_helper->catalogue()->picturesOrdering()
            ));

            return $this->_helper->json($this->_helper->pic->gallery2($select, array(
                'page'      => $this->getParam('page'),
                'pictureId' => $this->getParam('pictureId')
            )));

        });
    }
}