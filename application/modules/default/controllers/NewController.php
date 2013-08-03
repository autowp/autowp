<?php
class NewController extends Zend_Controller_Action
{
    protected $_perPage = 18;
    protected $_urlDateFormat = 'yyyy-MM-dd';

    protected function _dateCount(Zend_Date $date)
    {
        $db = $this->_helper->catalogue()->getPictureTable()->getAdapter();

        return $db->fetchOne(
            $db->select()
                ->from('pictures', new Zend_Db_Expr('COUNT(1)'))
                ->where('status = ?', Pictures::STATUS_ACCEPTED)
                ->where('accept_datetime >= ?', $date->toString('yyyy-MM-dd 00:00:00'))
                ->where('accept_datetime <= ?', $date->toString('yyyy-MM-dd 23:59:59'))
                ->limit(1)
        );
    }

    protected function _redirectToLastDate()
    {
        $pictureTable = $this->_helper->catalogue()->getPictureTable();

        $lastPicture = $pictureTable->fetchRow(array(
            'status = ?' => Pictures::STATUS_ACCEPTED
        ), 'accept_datetime desc');

        if (!$lastPicture) {
            return $this->_forward('notfound', 'error');
        }

        $lastDate = $lastPicture->getDate('accept_datetime');
        if (!$lastDate) {
            throw new Exception('Date is empty');
        }

        return $this->_redirect($this->_helper->url->url(array(
            'date' => $lastDate->get($this->_urlDateFormat),
            'page' => null
        )));
    }

    public function indexAction()
    {
        $pictureTable = $this->_helper->catalogue()->getPictureTable();
        $db = $pictureTable->getAdapter();

        $date = trim($this->_getParam('date'));
        if (!$date) {
            return $this->_redirectToLastDate();
        }

        $anyPictureOfDate = $pictureTable->fetchRow(
            $pictureTable->select(true)
                ->where('status = ?', Pictures::STATUS_ACCEPTED)
                ->where('accept_datetime >= ?', $date)
                ->where('accept_datetime <= ?', $date.' 23:59:59')
                ->limit(1)
        );

        if (!$anyPictureOfDate) {
            return $this->_forward('notfound', 'error');
        }

        $date = $anyPictureOfDate->getDate('accept_datetime');
        if (!$date) {
            throw new Exception('Date is empty');
        }

        // for date formatting fix
        $this->_setParam('date', $date->toString($this->_urlDateFormat));

        // выбираем дату днем раньше
        $prevDate = $db->fetchOne(
            $db->select()
                ->from('pictures', new Zend_Db_Expr('DATE(accept_datetime)'))
                ->where('status = ?', Pictures::STATUS_ACCEPTED)
                ->where('accept_datetime < ?', $date->toString('yyyy-MM-dd 00:00:00'))
                ->order('accept_datetime DESC')
                ->limit(1)
        );
        if ($prevDate) {
            $prevDate = new Zend_Date($prevDate, 'yyyy-MM-dd');
        }

        $nextDate = $db->fetchOne(
            $db->select()
                ->from('pictures', new Zend_Db_Expr('DATE(accept_datetime)'))
                ->where('status = ?', Pictures::STATUS_ACCEPTED)
                ->where('accept_datetime > ?', $date->toString('yyyy-MM-dd 23:59:59'))
                ->order('accept_datetime')
                ->limit(1)
        );
        if ($nextDate) {
            $nextDate = new Zend_Date($nextDate, 'yyyy-MM-dd');
        }

        $select = $pictureTable->select(true)
            ->where('status = ?', Pictures::STATUS_ACCEPTED)
            ->where('accept_datetime >= ?', $date->toString('yyyy-MM-dd 00:00:00'))
            ->where('accept_datetime <= ?', $date->toString('yyyy-MM-dd 23:59:59'))
            ->order('accept_datetime DESC');

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage($this->_perPage)
            ->setCurrentPageNumber($this->_getParam('page'));

        $this->view->assign(array(
            'urlDateFormat' => $this->_urlDateFormat,
            'paginator'     => $paginator,
            'date'          => $date,
            'prevDate'      => $prevDate,
            'nextDate'      => $nextDate,
            'count'         => $this->_dateCount($date),
            'prevCount'     => $prevDate ? $this->_dateCount($prevDate) : 0,
            'nextCount'     => $nextDate ? $this->_dateCount($nextDate) : 0,
        ));
    }
}