<?php

use Application\Service\DayPictures;
use Application\Model\Brand;

class InboxController extends Zend_Controller_Action
{
    private $_perPage = 18;

    private function _assignBrandControl($brand = null)
    {
        $brandModel = new Brand();
        $language = $this->_helper->language();

        $brands = $brandModel->getList($language, function($select) use ($language) {
            $select
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join('pictures', 'car_parent_cache.car_id = pictures.car_id', null)
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->where('pictures.status = ?', Picture::STATUS_INBOX)
                ->group('brands.id')
                ->bind([
                    'language' => $language
                ]);
        });

        $url = $this->_helper->url->url(array(
            'brand' => null
        ));
        $brandOptions = [
            $url => $this->view->translate('all-link')
        ];
        foreach ($brands as $iBrand) {
            $url = $this->_helper->url->url([
                'brand' => $iBrand['catname'],
                'page'  => null
            ]);
            $brandOptions[$url] = $iBrand['name'];
        }

        $currentBrandUrl = $this->_helper->url->url([
            'brand' => $brand ? $brand['catname'] : null,
            'page'  => null
        ]);

        $this->view->assign([
            'brands' => $brandOptions,
            'brand'  => $currentBrandUrl,
        ]);
    }

    public function indexAction()
    {
        $brandModel = new Brand();
        $language = $this->_helper->language();
        
        $brand = $brandModel->getBrandByCatname($this->getParam('brand'), $language);

        $pictureTable = $this->_helper->catalogue()->getPictureTable();

        $select = $pictureTable->select(true)
            ->where('pictures.status = ?', Picture::STATUS_INBOX);
        if ($brand) {
            $select
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand['id'])
                ->group('pictures.id');
        }

        $service = new DayPictures(array(
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