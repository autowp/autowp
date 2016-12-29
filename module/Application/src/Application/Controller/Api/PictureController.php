<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

use Application\Model\CarOfDay;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Vehicle;

class PictureController extends AbstractActionController
{
    /**
     * @var CarOfDay
     */
    private $carOfDay;

    public function __construct(CarOfDay $carOfDay)
    {
        $this->carOfDay = $carOfDay;
    }

    public function randomPictureAction()
    {
        $pictureTable = new Picture();

        $select = $pictureTable->select(true)
            ->where('pictures.status IN (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
            ->order('rand() desc')
            ->limit(1);

        $pictureRow = $pictureTable->fetchRow($select);

        $result = [
            'status' => false
        ];

        if ($pictureRow) {
            $imageInfo = $this->imageStorage()->getImage($pictureRow->image_id);
            $result = [
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $this->pic()->name($pictureRow, $this->language()),
                'page'   => $this->pic()->url($pictureRow->identity, true)
            ];
        }

        return new JsonModel($result);
    }

    public function newPictureAction()
    {
        $pictureTable = new Picture();

        $select = $pictureTable->select(true)
            ->where('pictures.status IN (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
            ->order('accept_datetime desc')
            ->limit(1);

        $pictureRow = $pictureTable->fetchRow($select);

        $result = [
            'status' => false
        ];

        if ($pictureRow) {
            $imageInfo = $this->imageStorage()->getImage($pictureRow->image_id);
            $result = [
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $this->pic()->name($pictureRow, $this->language()),
                'page'   => $this->pic()->url($pictureRow->identity, true)
            ];
        }

        return new JsonModel($result);
    }

    public function carOfDayPictureAction()
    {
        $carId = $this->carOfDay->getCurrent();

        $pictureRow = null;

        if ($carId) {
            $carTable = new Vehicle();
            $pictureTable = new Picture();

            $carRow = $carTable->find($carId)->current();
            if ($carRow) {
                foreach ([31, null] as $groupId) {
                    $select = $pictureTable->select(true)
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                        ->where('pictures.status IN (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
                        ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                        ->where('item_parent_cache.parent_id = ?', $carRow->id)
                        ->limit(1);

                    if ($groupId) {
                        $select
                            ->join(
                                ['mp' => 'perspectives_groups_perspectives'],
                                'picture_item.perspective_id = mp.perspective_id',
                                null
                            )
                            ->where('mp.group_id = ?', $groupId)
                            ->order([
                                'mp.position',
                                'pictures.width DESC', 'pictures.height DESC'
                            ]);
                    } else {
                        $select
                            ->order([
                                'pictures.width DESC', 'pictures.height DESC'
                            ]);
                    }

                    $pictureRow = $pictureTable->fetchRow($select);
                    if ($pictureRow) {
                        break;
                    }
                }
            }
        }

        $result = [
            'status' => false
        ];

        if ($pictureRow) {
            $imageInfo = $this->imageStorage()->getImage($pictureRow->image_id);
            $result = [
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $this->pic()->name($pictureRow, $this->language()),
                'page'   => $this->pic()->url($pictureRow->identity, true)
            ];
        }

        return new JsonModel($result);
    }
}
