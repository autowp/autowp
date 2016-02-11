<?php

use Application\Service\DayPictures;

class NewController extends Zend_Controller_Action
{
    private $_perPage = 18;

    public function indexAction()
    {
        $pictureTable = $this->_helper->catalogue()->getPictureTable();

        $service = new DayPictures(array(
            'timezone'     => $this->_helper->user()->timezone(),
            'dbTimezone'   => MYSQL_TIMEZONE,
            'select'       => $pictureTable->select(true)
                ->where('pictures.status = ?', Picture::STATUS_ACCEPTED),
            'orderColumn'  => 'accept_datetime',
            //'minDate'      => Zend_Date::now()->subMonth(1),
            'currentDate'  => $this->_getParam('date'),
        ));

        if (!$service->haveCurrentDate()) {
            $lastDate = $service->getLastDateStr();
            if (!$lastDate) {
                return $this->_forward('notfound', 'error');
            }

            return $this->_redirect($this->_helper->url->url(array(
                'date' => $lastDate,
                'page' => null
            )));
        }

        if (!$service->haveCurrentDayPictures()) {
            return $this->_forward('notfound', 'error');
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

        $this->view->assign(array(
            'picturesData' => $picturesData,
            'paginator' => $paginator,
            'prev'      => array(
                'date'  => $service->getPrevDate(),
                'count' => $service->getPrevDateCount(),
                'url'   => $this->_helper->url->url(array(
                    'date' => $service->getPrevDateStr()
                ), 'new', true)
            ),
            'current'   => array(
                'date'  => $service->getCurrentDate(),
                'count' => $service->getCurrentDateCount(),
            ),
            'next'      => array(
                'date'  => $service->getNextDate(),
                'count' => $service->getNextDateCount(),
                'url'   => $this->_helper->url->url(array(
                    'date' => $service->getNextDateStr()
                ), 'new', true)
            ),
        ));
    }
}