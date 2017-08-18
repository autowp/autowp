<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Paginator\Paginator;

use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;

use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Service\DayPictures;
use Application\Service\SpecificationsService;

class NewController extends AbstractActionController
{
    const PER_PAGE = 50;

    const ROUTE = 'new';

    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var PictureItem
     */
    private $pictureItem;

    public function __construct(
        ItemNameFormatter $itemNameFormatter,
        SpecificationsService $specsService,
        Item $itemModel,
        Picture $picture,
        PictureItem $pictureItem
    ) {
        $this->itemNameFormatter = $itemNameFormatter;
        $this->specsService = $specsService;
        $this->itemModel = $itemModel;
        $this->picture = $picture;
        $this->pictureItem = $pictureItem;
    }

    public function indexAction()
    {
        $service = new DayPictures([
            'timezone'     => $this->user()->timezone(),
            'dbTimezone'   => MYSQL_TIMEZONE,
            'select'       => $this->picture->getPictureTable()->select(true)
                ->where('pictures.status = ?', Picture::STATUS_ACCEPTED),
            'orderColumn'  => 'accept_datetime',
            'currentDate'  => $this->params('date'),
        ]);

        if (! $service->haveCurrentDate()) {
            $lastDate = $service->getLastDateStr();
            if (! $lastDate) {
                return $this->notFoundAction();
            }

            return $this->redirect()->toUrl($this->url()->fromRoute(self::ROUTE . '/date', [
                'date' => $lastDate
            ]));
        }

        $currentDateStr = $service->getCurrentDateStr();
        if ($this->params('date') != $currentDateStr) {
            return $this->redirect()->toUrl($this->url()->fromRoute(self::ROUTE . '/date', [
                'date' => $currentDateStr
            ]));
        }

        if (! $service->haveCurrentDayPictures()) {
            return $this->notFoundAction();
        }

        $select = $service->getCurrentDateSelect();

        $paginator = new Paginator(
            new Zend1DbTableSelect($select)
        );
        $paginator
            ->setItemCountPerPage(self::PER_PAGE)
            ->setCurrentPageNumber($this->params('page'));

        $items = [];
        foreach ($paginator->getCurrentItems() as $pictureRow) {
            $itemIds = $this->pictureItem->getPictureItems($pictureRow['id']);
            if (count($itemIds) != 1) {
                $items[] = [
                    'type'    => 'picture',
                    'picture' => $pictureRow
                ];
            } else {
                $itemId = $itemIds[0];

                $found = false;
                foreach ($items as &$item) {
                    if ($item['type'] == 'item' && $item['item_id'] == $itemId) {
                        $item['pictures'][] = $pictureRow;
                        $found = true;
                        break;
                    }
                }
                unset($item);

                if (! $found) {
                    $items[] = [
                        'item_id'  => $itemId,
                        'type'     => 'item',
                        'pictures' => [$pictureRow]
                    ];
                }
            }
        }

        // convert single picture items to picture record
        $tempItems = $items;
        $items = [];
        foreach ($tempItems as $item) {
            if ($item['type'] != 'item') {
                $items[] = $item;
                continue;
            }

            if (count($item['pictures']) <= 2) {
                foreach ($item['pictures'] as $picture) {
                    $items[] = [
                        'type'    => 'picture',
                        'picture' => $picture
                    ];
                }
            } else {
                $items[] = $item;
            }
        }

        // merge sibling single items
        $tmpItems = $items;
        $items = [];
        $picturesBuffer = [];
        foreach ($tmpItems as $itemId => $item) {
            if ($item['type'] == 'item') {
                if (count($picturesBuffer) > 0) {
                    $items[] = [
                        'type'     => 'pictures',
                        'pictures' => $picturesBuffer
                    ];
                    $picturesBuffer = [];
                }

                $items[$itemId] = $item;
            } else {
                $picturesBuffer[] = $item['picture'];
            }
        }

        if (count($picturesBuffer) > 0) {
            $items[] = [
                'type'     => 'pictures',
                'pictures' => $picturesBuffer
            ];
            $picturesBuffer = [];
        }

        foreach ($items as &$item) {
            if ($item['type'] == 'item') {
                $itemRow = $this->itemModel->getRow(['id' => $item['item_id']]);

                $ids = [];
                foreach ($item['pictures'] as $row) {
                    $ids[] = $row['id'];
                }

                $item['listData'] = $this->car()->listData([$itemRow], [
                    'thumbColumns'   => 6,
                    'disableDetailsLink' => true,
                    'disableSpecs'       => true,
                    'pictureFetcher' => new \Application\Model\Item\NewPictureFetcher([
                        'pictureModel' => $this->picture,
                        'itemModel'    => $this->itemModel,
                        'pictureIds'   => $ids
                    ]),
                    'listBuilder' => new \Application\Model\Item\ListBuilder\NewPicturesListBuilder([
                        'date'         => $currentDateStr,
                        'pictureIds'   => $ids,
                        'catalogue'    => $this->catalogue(),
                        'router'       => $this->getEvent()->getRouter(),
                        'picHelper'    => $this->getPluginManager()->get('pic'),
                        'specsService' => $this->specsService
                    ])
                ]);
            } else {
                $item['picture'] = $this->pic()->listData($item['pictures'], [
                    'width' => 6
                ]);
            }
        }
        unset($item);


        return [
            'items' => $items,
            'paginator' => $paginator,
            'prev'      => [
                'date'  => $service->getPrevDate(),
                'count' => $service->getPrevDateCount(),
                'url'   => $this->url()->fromRoute(self::ROUTE . '/date', [
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
                'url'   => $this->url()->fromRoute(self::ROUTE . '/date', [
                    'date' => $service->getNextDateStr()
                ])
            ],
            'paginatorRoute' => self::ROUTE . '/date/page',
            'urlParams' => [
                'date'  => $this->params('date')
            ],
        ];
    }

    public function itemAction()
    {
        $item = $this->itemModel->getRow(['id' => (int)$this->params('item_id')]);
        if (! $item) {
            return $this->notFoundAction();
        }

        $select = $this->picture->getPictureTable()->select(true)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->where('picture_item.item_id = ?', $item['id'])
            ->where('pictures.status = ?', Picture::STATUS_ACCEPTED);

        $service = new DayPictures([
            'timezone'     => $this->user()->timezone(),
            'dbTimezone'   => MYSQL_TIMEZONE,
            'select'       => $select,
            'orderColumn'  => 'accept_datetime',
            'currentDate'  => $this->params('date'),
        ]);

        $paginator = $service->getPaginator()
            ->setItemCountPerPage(30)
            ->setCurrentPageNumber($this->params('page'));

        if ($paginator->getTotalItemCount() <= 0) {
            return $this->notFoundAction();
        }

        $select = $service->getCurrentDateSelect()
            ->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $picturesData = $this->pic()->listData($select, [
            'width' => 6
        ]);

        $language = $this->language();

        $carFullName = $this->itemNameFormatter->format(
            $this->itemModel->getNameData($item, $language),
            $language
        );

        return [
            'picturesData' => $picturesData,
            'paginator' => $paginator,
            'paginatorRoute' => self::ROUTE . '/date/item',
            'urlParams' => [
                'date'    => $this->params('date'),
                'item_id' => $this->params('item_id')
            ],
            'dateTime'    => $service->getCurrentDate(),
            'dateStr'     => $service->getCurrentDateStr(),
            'carFullName' => $carFullName
        ];
    }
}
