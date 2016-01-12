<?php

class InboxController extends Zend_Controller_Action
{
    private $_perPage = 18;

    private function _assignBrandControl(Brands_Row $brand = null)
    {
        $brandTable = $this->_helper->catalogue()->getBrandTable();

        $db = $brandTable->getAdapter();

        $brandRows = $db->fetchAll(
            $db->select()
                ->from('brands', ['folder', 'name' => 'IFNULL(brand_language.name, brands.caption)'])
                ->joinLeft('brand_language', 'brands.id = brand_language.brand_id and brand_language.language = :language', null)
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join('pictures', 'car_parent_cache.car_id = pictures.car_id', null)
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->where('pictures.status = ?', Picture::STATUS_INBOX)
                ->group('brands.id')
                ->order(array('brands.position', 'name'))
                ->bind(array(
                    'language' => $this->_helper->language()
                ))
        );
        $url = $this->_helper->url->url(array(
            'brand' => null
        ));
        $brandOptions = array(
            $url => $this->view->translate('all-link')
        );
        foreach ($brandRows as $brandRow) {
            $url = $this->_helper->url->url(array(
                'brand' => $brandRow['folder'],
                'page'  => null
            ));
            $brandOptions[$url] = $brandRow['name'];
        }

        $currentBrandUrl = $this->_helper->url->url(array(
            'brand' => $brand ? $brand->folder : null,
            'page'  => null
        ));

        $this->view->assign(array(
            'brands' => $brandOptions,
            'brand'  => $currentBrandUrl,
        ));
    }

    public function indexAction()
    {
        $brandTable = $this->_helper->catalogue()->getBrandTable();

        $brand = $brandTable->findRowByCatname($this->_getParam('brand'));

        $pictureTable = $this->_helper->catalogue()->getPictureTable();

        $select = $pictureTable->select(true)
            ->where('pictures.status = ?', Picture::STATUS_INBOX);
        if ($brand) {
            $select
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand->id)
                ->group('pictures.id');
        }

        $service = new Application_Service_DayPictures(array(
            'timezone'     => $this->_helper->user()->timezone(),
            'dbTimezone'   => MYSQL_TIMEZONE,
            'select'       => $select,
            'orderColumn'  => 'add_date',
            'currentDate'  => $this->_getParam('date')
        ));

        if (!$service->haveCurrentDate() || !$service->haveCurrentDayPictures()) {
            $lastDate = $service->getLastDateStr();
            if (!$lastDate) {
                return $this->_forward('notfound', 'error');
            }

            return $this->_redirect($this->_helper->url->url(array(
                'date' => $lastDate,
                'page' => null
            )));
        }

        // for date formatting fix
        $this->_setParam('date', $service->getCurrentDateStr());

        $paginator = $service->getPaginator()
            ->setItemCountPerPage($this->_perPage)
            ->setCurrentPageNumber($this->_getParam('page'));

        $select = $service->getCurrentDateSelect()
            ->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $picturesData = $this->_helper->pic->listData($select, array(
            'width' => 6
        ));

        $this->_assignBrandControl($brand);

        $this->view->assign(array(
            'picturesData' => $picturesData,
            'paginator' => $paginator,
            'prev'      => array(
                'date'  => $service->getPrevDate(),
                'count' => $service->getPrevDateCount(),
                'url'   => $this->_helper->url->url(array(
                    'date' => $service->getPrevDateStr(),
                    'page' => null
                ), 'inbox')
            ),
            'current'   => array(
                'date'  => $service->getCurrentDate(),
                'count' => $service->getCurrentDateCount(),
            ),
            'next'      => array(
                'date'  => $service->getNextDate(),
                'count' => $service->getNextDateCount(),
                'url'   => $this->_helper->url->url(array(
                    'date' => $service->getNextDateStr(),
                    'page' => null
                ), 'inbox')
            ),
        ));
    }
}