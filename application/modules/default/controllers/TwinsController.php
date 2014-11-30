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

        $this->view->assign(array(
            'group'   => $group,
            'carList' => $this->_getTwins()->getGroupCars($group['id'])
        ));
    }

    public function picturesAction()
    {
        $twins = $this->_getTwins();

        $group = $twins->getGroup($this->_getParam('twins_group_id'));
        if (!$group) {
            return $this->_forward('notfound', 'error');
        }

        $this->view->assign(array(
            'group'     => $group,
            'paginator' => $twins->getGroupPicturesPaginator($group['id'], array(
                    'ordering' => $this->_helper->catalogue()->picturesOrdering()
                ))
                ->setItemCountPerPage(16)
                ->setCurrentPageNumber($this->_getParam('page')),
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
                'disableSpecs'         => true
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

        $specService = new Application_Service_Specifications();

        $groups = array();
        foreach ($list as $group) {
            $carList = $this->_getTwins()->getGroupCars($group->id);
            $picturesShown = 0;
            $cars = array();
            $hasSpecs = false;

            foreach ($carList as $car) {
                $picture = $pictureTable->fetchRow(
                    $pictureTable->select(true)
                        ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                        ->where('car_parent_cache.parent_id = ?', $car->id)
                        ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                        ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
                        ->order(array(
                            new Zend_Db_Expr('pictures.perspective_id=7 DESC'),
                            new Zend_Db_Expr('pictures.perspective_id=8 DESC')
                        ))
                        ->limit(1)
                );

                if ($picture) {
                    $picturesShown++;
                }

                $hasSpecs = $hasSpecs || $specService->hasSpecs(1, $car->id);

                $cars[] = array(
                    'picture' => $picture,
                    'name'    => $car->getFullName()
                );
            }

            $commentsStat = $ctTable->getTopicStat(
                Comment_Message::TWINS_TYPE_ID,
                $group->id
            );
            $msgCount = $commentsStat['messages'];

            $picturesCount = $this->_getTwins()->getGroupPicturesCount($group->id);

            $groups[] = array(
                'name'          => $group->name,
                'cars'          => $cars,
                'picturesShown' => $picturesShown,
                'picturesCount' => $picturesCount,
                'hasSpecs'      => $hasSpecs,
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
}