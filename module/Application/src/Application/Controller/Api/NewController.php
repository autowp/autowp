<?php

namespace Application\Controller\Api;

use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\Paginator;
use Zend\View\Model\JsonModel;

use Application\Hydrator\Api\RestHydrator;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Service\DayPictures;

class NewController extends AbstractRestfulController
{
    const PER_PAGE = 18;
    const BRAND_ALL = 'all';

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var InputFilter
     */
    private $inputFilter;

    /**
     * @var PictureItem
     */
    private $pictureItem;

    /**
     * @var RestHydrator
     */
    private $pictureHydrator;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var RestHydrator
     */
    private $itemHydrator;

    public function __construct(
        Picture $picture,
        Item $itemModel,
        PictureItem $pictureItem,
        InputFilter $inputFilter,
        RestHydrator $pictureHydrator,
        RestHydrator $itemHydrator
    ) {
        $this->picture = $picture;
        $this->inputFilter = $inputFilter;
        $this->pictureItem = $pictureItem;
        $this->pictureHydrator = $pictureHydrator;
        $this->pictureThumbnailHydrator = clone $pictureHydrator;
        $this->itemModel = $itemModel;
        $this->itemHydrator = $itemHydrator;
    }

    public function indexAction()
    {
        $user = $this->user()->get();

        $this->inputFilter->setData($this->params()->fromQuery());

        if (! $this->inputFilter->isValid()) {
            return $this->inputFilterResponse($this->inputFilter);
        }

        $values = $this->inputFilter->getValues();

        $language = $this->language();

        $select = $this->picture->getTable()->getSql()->select()
            ->where(['pictures.status' => Picture::STATUS_ACCEPTED]);

        $service = new DayPictures([
            'picture'      => $this->picture,
            'timezone'     => $this->user()->timezone(),
            'dbTimezone'   => MYSQL_TIMEZONE,
            'select'       => $select,
            'orderColumn'  => 'accept_datetime',
            'currentDate'  => $values['date']
        ]);

        if (! $service->haveCurrentDate() || ! $service->haveCurrentDayPictures()) {
            $lastDate = $service->getLastDateStr();

            if (! $lastDate) {
                return $this->notFoundAction();
            }

            $service->setCurrentDate($lastDate);
        }

        $paginator = $service->getPaginator()
            ->setItemCountPerPage(self::PER_PAGE)
            ->setCurrentPageNumber($values['page']);

        $prevDate = $service->getPrevDate();
        $currentDate = $service->getCurrentDate();
        $nextDate = $service->getNextDate();

        $groupsData = $this->splitPictures($paginator->getCurrentItems());

        $this->pictureHydrator->setOptions([
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null,
            'fields'   => isset($values['fields']['pictures']) ? $values['fields']['pictures'] : []
        ]);

        $this->pictureThumbnailHydrator->setOptions([
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null,
            'fields'   => isset($values['fields']['item_pictures']) ? $values['fields']['item_pictures'] : []
        ]);

        $this->itemHydrator->setOptions([
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null,
            'fields'   => isset($values['fields']['item']) ? $values['fields']['item'] : []
        ]);

        $groups = [];
        foreach ($groupsData as $groupData) {
            $group = [
                'type' => $groupData['type']
            ];
            if ($groupData['type'] == 'item') {
                $itemRow = $this->itemModel->getRow(['id' => $groupData['item_id']]);
                $group['item'] = $this->itemHydrator->extract($itemRow);

                $ids = [];
                foreach ($groupData['pictures'] as $picture) {
                    $ids[] = $picture['id'];
                }

                $pictureRows = $this->picture->getRows([
                    'id'    => $ids,
                    'item'  => [
                        'id' => $groupData['item_id']
                    ],
                    'limit' => 6,
                    'order' => 'accept_datetime_desc'
                ]);

                $group['pictures'] = [];
                foreach ($pictureRows as $row) {
                    $group['pictures'][] = $this->pictureThumbnailHydrator->extract($row);
                }

                $group['total_pictures'] = $this->picture->getCount([
                    'status'      => Picture::STATUS_ACCEPTED,
                    'item_id'     => $groupData['item_id'],
                    'accept_date' => $values['date'],
                    'timezone'    => $this->user()->timezone()
                ]);

            } else {
                $group['pictures'] = [];
                foreach ($groupData['pictures'] as $row) {
                    $group['pictures'][] = $this->pictureHydrator->extract($row);
                }
            }

            $groups[] = $group;
        }

        return new JsonModel([
            'groups'    => $groups,
            'paginator' => $paginator->getPages(),
            'prev'      => [
                'date'  => $prevDate ? $prevDate->format('Y-m-d') : null,
                'count' => $service->getPrevDateCount()
            ],
            'current'   => [
                'date'  => $currentDate ? $currentDate->format('Y-m-d') : null,
                'count' => $service->getCurrentDateCount(),
            ],
            'next'      => [
                'date'  => $nextDate ? $nextDate->format('Y-m-d') : null,
                'count' => $service->getNextDateCount()
            ]
        ]);
    }

    private function splitPictures($pictures)
    {
        $items = [];
        foreach ($pictures as $pictureRow) {
            $itemIds = $this->pictureItem->getPictureItems($pictureRow['id'], PictureItem::PICTURE_CONTENT);
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

        /*foreach ($items as &$item) {
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
        unset($item);*/

        return $items;
    }
}
