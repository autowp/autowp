<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable\Picture;
use Application\Service\DayPictures;

class NewController extends AbstractActionController
{
    const PER_PAGE = 18;

    public function indexAction()
    {
        $pictureTable = new Picture();

        $service = new DayPictures([
            'timezone'     => $this->user()->timezone(),
            'dbTimezone'   => MYSQL_TIMEZONE,
            'select'       => $pictureTable->select(true)
                ->where('pictures.status = ?', Picture::STATUS_ACCEPTED),
            'orderColumn'  => 'accept_datetime',
            'currentDate'  => $this->params('date'),
        ]);

        if (! $service->haveCurrentDate()) {
            $lastDate = $service->getLastDateStr();
            if (! $lastDate) {
                return $this->notFoundAction();
            }

            return $this->redirect()->toUrl($this->url()->fromRoute('new', [
                'date' => $lastDate,
                'page' => null
            ]));
        }

        $currentDateStr = $service->getCurrentDateStr();
        if ($this->params('date') != $currentDateStr) {
            return $this->redirect()->toUrl($this->url()->fromRoute('new', [
                'date' => $currentDateStr,
                'page' => null
            ]));
        }

        if (! $service->haveCurrentDayPictures()) {
            return $this->notFoundAction();
        }

        // for date formatting fix
        //$this->_setParam('date', $service->getCurrentDateStr());

        $paginator = $service->getPaginator()
            ->setItemCountPerPage(self::PER_PAGE)
            ->setCurrentPageNumber($this->params('page'));

        $select = $service->getCurrentDateSelect()
            ->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $picturesData = $this->pic()->listData($select, [
            'width' => 6
        ]);

        return [
            'picturesData' => $picturesData,
            'paginator' => $paginator,
            'prev'      => [
                'date'  => $service->getPrevDate(),
                'count' => $service->getPrevDateCount(),
                'url'   => $this->url()->fromRoute('new', [
                    'date' => $service->getPrevDateStr()
                ])
            ],
            'current'   => [
                'date'  => $service->getCurrentDate(),
                'count' => $service->getCurrentDateCount(),
            ],
            'next'      => [
                'date'  => $service->getNextDate(),
                'count' => $service->getNextDateCount(),
                'url'   => $this->url()->fromRoute('new', [
                    'date' => $service->getNextDateStr()
                ])
            ],
            'urlParams' => [
                'date'  => $this->params('date')
            ]
        ];
    }
}
