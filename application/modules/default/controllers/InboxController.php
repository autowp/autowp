<?php
class InboxController extends Zend_Controller_Action
{
    protected $_perPage = 18;
    protected $_urlDateFormat = 'yyyy-MM-dd';

    /**
     * Builds common part of SQL query
     * @param Brands_Row $brand
     * @return Zend_Db_Table_Select
     */
    protected function _pictureSelect(Brands_Row $brand = null)
    {
        $pictureTable = $this->_helper->catalogue()->getPictureTable();

        $select = $pictureTable->select(true)
            ->where('pictures.status = ?', Picture::STATUS_INBOX);
        if ($brand) {
            $select
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand->id);
        }
        return $select;
    }

    protected function _dateCount(Zend_Date $date, Brands_Row $brand = null)
    {
        $pictureTable = $this->_helper->catalogue()->getPictureTable();
        $db = $pictureTable->getAdapter();

        $select = $db->select()
            ->from($pictureTable->info('name'), new Zend_Db_Expr('COUNT(1)'))
            ->where('pictures.status = ?', Picture::STATUS_INBOX)
            ->where('pictures.add_date >= ?', $date->toString('yyyy-MM-dd 00:00:00'))
            ->where('pictures.add_date <= ?', $date->toString('yyyy-MM-dd 23:59:59'));
        if ($brand) {
            $select
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand->id);
        }

        return $db->fetchOne($select);
    }

    protected function _redirectToLastDate(Brands_Row $brand = null)
    {
        $pictureTable = $this->_helper->catalogue()->getPictureTable();

        $lastPicture = $pictureTable->fetchRow(
            $this->_pictureSelect($brand)
                ->order('add_date desc')
        );

        if (!$lastPicture) {
            return $this->_forward('notfound', 'error');
        }

        $lastDate = $lastPicture->getDate('add_date');
        if (!$lastDate) {
            throw new Exception('Date is empty');
        }

        return $this->_redirect($this->_helper->url->url(array(
            'date' => $lastDate->get($this->_urlDateFormat),
            'page' => null
        )));
    }

    protected function _assignBrandControl(Brands_Row $brand = null)
    {
        $brandTable = $this->_helper->catalogue()->getBrandTable();

        $brandRows = $brandTable->fetchAll(
            $brandTable->select(true)
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join('pictures', 'car_parent_cache.car_id = pictures.car_id', null)
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->where('pictures.status = ?', Picture::STATUS_INBOX)
                ->group('brands.id')
                ->order(array('brands.position', 'brands.caption'))
        );
        $url = $this->_helper->url->url(array(
            'brand' => null
        ));
        $brandOptions = array(
            $url => 'все'
        );
        foreach ($brandRows as $brandRow) {
            $url = $this->_helper->url->url(array(
                'brand' => $brandRow->folder,
                'page'  => null
            ));
            $brandOptions[$url] = $brandRow->caption;
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

        $date = trim($this->_getParam('date'));
        if (!$date) {
            return $this->_redirectToLastDate($brand ? $brand : null);
        }

        $pictureTable = $this->_helper->catalogue()->getPictureTable();
        $pictureTableAdapter = $pictureTable->getAdapter();

        $currentDatePicture = $pictureTable->fetchRow(
            $this->_pictureSelect($brand)
                ->where('pictures.add_date >= ?', $date.' 00:00:00')
                ->where('pictures.add_date <= ?', $date.' 23:59:59')
        );

        if (!$currentDatePicture) {
            return $this->_redirectToLastDate($brand ? $brand : null);
        }

        $date = $currentDatePicture->getDate('add_date');
        if (!$date) {
            throw new Exception('Date is empty');
        }

        $this->_assignBrandControl($brand);

        // for date formatting fix
        $this->_setParam('date', $date->toString($this->_urlDateFormat));

        // выбираем дату днем раньше
        $prevDatePicture = $pictureTable->fetchRow(
            $this->_pictureSelect($brand)
                ->where('pictures.add_date < ?', $date->toString('yyyy-MM-dd 00:00:00'))
                ->order('pictures.add_date DESC')
        );
        $prevDate = $prevDatePicture ? $prevDatePicture->getDate('add_date') : null;

        $nextDatePicture = $pictureTable->fetchRow(
            $this->_pictureSelect($brand)
                ->where('pictures.add_date > ?', $date->toString('yyyy-MM-dd 23:59:59'))
                ->order('pictures.add_date')
        );
        $nextDate = $nextDatePicture ? $nextDatePicture->getDate('add_date') : null;

        $select = $this->_pictureSelect($brand)
            ->where('pictures.add_date >= ?', $date->toString('yyyy-MM-dd 00:00:00'))
            ->where('pictures.add_date <= ?', $date->toString('yyyy-MM-dd 23:59:59'))
            ->order('pictures.add_date DESC');

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage($this->_perPage)
            ->setCurrentPageNumber($this->_getParam('page'));

        $this->view->assign(array(
            'urlDateFormat' => $this->_urlDateFormat,
            'paginator'     => $paginator,
            'date'          => $date,
            'prevDate'      => $prevDate,
            'nextDate'      => $nextDate,
            'count'         => $this->_dateCount($date, $brand),
            'prevCount'     => $prevDate ? $this->_dateCount($prevDate, $brand) : 0,
            'nextCount'     => $nextDate ? $this->_dateCount($nextDate, $brand) : 0
        ));
    }
}