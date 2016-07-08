<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Service\DayPictures;
use Application\Model\Brand;

use Picture;

class InboxController extends AbstractActionController
{
    const PER_PAGE = 18;
    const BRAND_ALL = 'all';

    private function getBrandControl($brand = null)
    {
        $brandModel = new Brand();
        $language = $this->language();

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

        $url = $this->url()->fromRoute('inbox', [
            'brand' => self::BRAND_ALL,
            'date'  => $this->params('date'),
            'page'  => null
        ]);
        $brandOptions = [
            $url => 'All' //$this->view->translate('all-link')
        ];
        foreach ($brands as $iBrand) {
            $url = $this->url()->fromRoute('inbox', [
                'brand' => $iBrand['catname'],
                'date'  => $this->params('date'),
                'page'  => null
            ]);
            $brandOptions[$url] = $iBrand['name'];
        }

        $currentBrandUrl = $this->url()->fromRoute('inbox', [
            'brand' => $brand ? $brand['catname'] : null,
            'date'  => $this->params('date'),
            'page'  => null
        ]);

        return [
            'brands' => $brandOptions,
            'brand'  => $currentBrandUrl,
        ];
    }

    public function indexAction()
    {
        $brandModel = new Brand();
        $language = $this->language();

        $brand = $brandModel->getBrandByCatname($this->params('brand'), $language);

        $pictureTable = new Picture();

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

        $brandCatname = $brand ? $brand['catname'] : self::BRAND_ALL;

        $service = new DayPictures([
            'timezone'     => $this->user()->timezone(),
            'dbTimezone'   => MYSQL_TIMEZONE,
            'select'       => $select,
            'orderColumn'  => 'add_date',
            'currentDate'  => $this->params('date')
        ]);

        if (!$service->haveCurrentDate() || !$service->haveCurrentDayPictures()) {
            $lastDate = $service->getLastDateStr();

            if (!$lastDate) {
                return $this->notFoundAction();
            }

            $url = $this->url()->fromRoute('inbox', [
                'brand' => $brandCatname,
                'date'  => $lastDate,
                'page'  => null
            ]);
            return $this->redirect()->toUrl($url);
        }

        $currentDateStr = $service->getCurrentDateStr();
        if ($this->params('date') != $currentDateStr) {
            $url = $this->url()->fromRoute('inbox', [
                'brand' => $brandCatname,
                'date'  => $currentDateStr,
                'page'  => null
            ]);
            return $this->redirect()->toUrl($url);
        }

        $paginator = $service->getPaginator2()
            ->setItemCountPerPage(self::PER_PAGE)
            ->setCurrentPageNumber($this->params('page'));

        $select = $service->getCurrentDateSelect()
            ->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $picturesData = $this->pic()->listData($select, [
            'width' => 6
        ]);


        return array_replace(
            $this->getBrandControl($brand), [
                'picturesData' => $picturesData,
                'paginator' => $paginator,
                'prev'      => [
                    'date'  => $service->getPrevDate(),
                    'count' => $service->getPrevDateCount(),
                    'url'   => $this->url()->fromRoute('inbox', [
                        'brand' => $brandCatname,
                        'date'  => $service->getPrevDateStr(),
                        'page'  => null
                    ])
                ],
                'current'   => [
                    'date'  => $service->getCurrentDate(),
                    'count' => $service->getCurrentDateCount(),
                ],
                'next'      => [
                    'date'  => $service->getNextDate(),
                    'count' => $service->getNextDateCount(),
                    'url'   => $this->url()->fromRoute('inbox', [
                        'brand' => $brandCatname,
                        'date'  => $service->getNextDateStr(),
                        'page'  => null
                    ])
                ],
                'urlParams' => [
                    'brand' => $brandCatname,
                    'date'  => $this->params('date')
                ]
            ]
        );
    }
}