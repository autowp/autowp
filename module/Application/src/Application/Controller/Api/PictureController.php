<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Application\Model\CarOfDay;

use Cars;
use Picture;

class PictureController extends AbstractActionController
{
    private function serverUrl($url)
    {
        $helper = new \Zend\View\Helper\ServerUrl();
        return $helper->__invoke($url);
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
                'page'   => $this->serverUrl($this->pic()->url($pictureRow->id, $pictureRow->identity))
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
                'page'   => $this->serverUrl($this->pic()->url($pictureRow->id, $pictureRow->identity))
            ];
        }

        return new JsonModel($result);
    }

    public function carOfDayPictureAction()
    {
        $ofDay = new CarOfDay();

        $carId = $ofDay->getCurrent();

        $pictureRow = null;

        if ($carId) {
            $carTable = new Cars();
            $pictureTable = new Picture();

            $carRow = $carTable->find($carId)->current();
            if ($carRow) {
                foreach ([31, null] as $groupId) {
                    $select = $pictureTable->select(true)
                        ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                        ->where('pictures.status IN (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
                        ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                        ->where('car_parent_cache.parent_id = ?', $carRow->id)
                        ->limit(1);

                    if ($groupId) {
                        $select
                            ->join(['mp' => 'perspectives_groups_perspectives'], 'pictures.perspective_id=mp.perspective_id', null)
                            ->where('mp.group_id = ?', $groupId)
                            ->order([
                                'mp.position',
                                'pictures.width DESC', 'pictures.height DESC'
                            ]);
                    } else {
                        $select
                            ->order([
                                'mp.position',
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
                'page'   => $this->serverUrl($this->pic()->url($pictureRow->id, $pictureRow->identity))
            ];
        }

        return new JsonModel($result);
    }
}
