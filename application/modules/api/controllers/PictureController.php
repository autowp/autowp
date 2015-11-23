<?php

class Api_PictureController extends Zend_Controller_Action
{
    public function randomPictureAction()
    {
        $pictureTable = $this->_helper->catalogue()->getPictureTable();

        $select = $pictureTable->select(true)
            ->where('pictures.status IN (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
            ->order('rand() desc')
            ->limit(1);

        $pictureRow = $pictureTable->fetchRow($select);

        $result = array(
            'status' => false
        );

        if ($pictureRow) {
            $imageStorage = $this->getInvokeArg('bootstrap')->getResource('imagestorage');
            $imageInfo = $imageStorage->getImage($pictureRow->image_id);
            $result = array(
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $pictureRow->getCaption(),
                'page'   => $this->view->serverUrl($this->_helper->pic->url($pictureRow->id, $pictureRow->identity))
            );
        }

        return $this->_helper->json($result);
    }

    public function newPictureAction()
    {
        $pictureTable = $this->_helper->catalogue()->getPictureTable();

        $select = $pictureTable->select(true)
            ->where('pictures.status IN (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
            ->order('accept_datetime desc')
            ->limit(1);

        $pictureRow = $pictureTable->fetchRow($select);

        $result = array(
            'status' => false
        );

        if ($pictureRow) {
            $imageStorage = $this->getInvokeArg('bootstrap')->getResource('imagestorage');
            $imageInfo = $imageStorage->getImage($pictureRow->image_id);
            $result = array(
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $pictureRow->getCaption(),
                'page'   => $this->view->serverUrl($this->_helper->pic->url($pictureRow->id, $pictureRow->identity))
            );
        }

        return $this->_helper->json($result);
    }

    public function carOfDayPictureAction()
    {
        $ofDayTable = new Of_Day();
        $row = $ofDayTable->fetchRow(array(
            'day_date<=CURDATE()'
        ), 'day_date DESC');

        $pictureRow = null;

        if ($row) {
            $carTable = $this->_helper->catalogue()->getCarTable();
            $pictureTable = $this->_helper->catalogue()->getPictureTable();

            $carRow = $carTable->find($row['car_id'])->current();
            if ($carRow) {
                foreach ([31, null] as $groupId) {
                    $select = $pictureTable->select(true)
                        ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                        ->where('pictures.status IN (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
                        ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                        ->where('car_parent_cache.parent_id = ?', $carRow->id)
                        ->limit(1);

                    if ($groupId) {
                        $select
                            ->join(array('mp' => 'perspectives_groups_perspectives'), 'pictures.perspective_id=mp.perspective_id', null)
                            ->where('mp.group_id = ?', $groupId)
                            ->order(array(
                                'mp.position',
                                'pictures.width DESC', 'pictures.height DESC'
                            ));
                    } else {
                        $select
                            ->order(array(
                                'mp.position',
                                'pictures.width DESC', 'pictures.height DESC'
                            ));
                    }

                    $pictureRow = $pictureTable->fetchRow($select);
                    if ($pictureRow) {
                        break;
                    }
                }
            }
        }

        $result = array(
            'status' => false
        );

        if ($pictureRow) {
            $imageStorage = $this->getInvokeArg('bootstrap')->getResource('imagestorage');
            $imageInfo = $imageStorage->getImage($pictureRow->image_id);
            $result = array(
                'status' => true,
                'url'    => $imageInfo->getSrc(),
                'name'   => $pictureRow->getCaption(),
                'page'   => $this->view->serverUrl($this->_helper->pic->url($pictureRow->id, $pictureRow->identity))
            );
        }

        return $this->_helper->json($result);
    }
}