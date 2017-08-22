<?php

namespace Application\Controller;

use Zend\Db\Sql;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Service\DayPictures;
use Application\Model\Brand;
use Application\Model\Item;
use Application\Model\Picture;

class InboxController extends AbstractActionController
{
    const PER_PAGE = 18;
    const BRAND_ALL = 'all';

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var Brand
     */
    private $brand;

    public function __construct(Picture $picture, Brand $brand)
    {
        $this->picture = $picture;
        $this->brand = $brand;
    }

    private function getBrandControl($brand = null)
    {
        $language = $this->language();

        $brands = $this->brand->getList($language, function (Sql\Select $select) {

            $subSelect = new Sql\Select('item');
            $subSelect->columns(['id'])
                ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
                ->join('picture_item', 'item_parent_cache.item_id = picture_item.item_id', [])
                ->join('pictures', 'picture_item.picture_id = pictures.id', [])
                ->where([
                    'item.item_type_id' => Item::BRAND,
                    'pictures.status'   => Picture::STATUS_INBOX
                ]);

            $select->where([
                new Sql\Predicate\In('item.id', $subSelect)
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
        $language = $this->language();

        $brand = $this->brand->getBrandByCatname((string)$this->params('brand'), $language);

        $select = $this->picture->getTable()->getSql()->select()
            ->where(['pictures.status' => Picture::STATUS_INBOX]);
        if ($brand) {
            $select
                ->join('picture_item', 'pictures.id = picture_item.picture_id', [])
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', [])
                ->where(['item_parent_cache.parent_id' => $brand['id']])
                ->group('pictures.id');
        }

        $brandCatname = $brand ? $brand['catname'] : self::BRAND_ALL;

        $service = new DayPictures([
            'picture'      => $this->picture,
            'timezone'     => $this->user()->timezone(),
            'dbTimezone'   => MYSQL_TIMEZONE,
            'select'       => $select,
            'orderColumn'  => 'add_date',
            'currentDate'  => $this->params('date')
        ]);

        if (! $service->haveCurrentDate() || ! $service->haveCurrentDayPictures()) {
            $lastDate = $service->getLastDateStr();

            if (! $lastDate) {
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

        $paginator = $service->getPaginator()
            ->setItemCountPerPage(self::PER_PAGE)
            ->setCurrentPageNumber($this->params('page'));

        $picturesData = $this->pic()->listData($paginator->getCurrentItems(), [
            'width' => 6
        ]);


        return array_replace(
            $this->getBrandControl($brand),
            [
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
