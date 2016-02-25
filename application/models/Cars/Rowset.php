<?php

/**
 * 
 * @author autow
 * @deprecated
 */
class Cars_Rowset extends Zend_Db_Table_Rowset
{
    public function getOrientedPictureList(array $perspectiveGroupIds)
    {
        $pictures = new Picture();
        $list = array();
        $carIds = array();
        foreach ($this as $car) {
            $carIds[] = $car->id;
        }

        if (count($carIds) > 0) {
            foreach ($perspectiveGroupIds as $groupId) {
                $picture = $pictures->fetchRow(
                    $pictures->select(true)
                        ->join('perspectives_groups_perspectives', 'perspectives_groups_perspectives.perspective_id=pictures.perspective_id', null)
                        ->where('perspectives_groups_perspectives.group_id = ?', $groupId)
                        ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                        ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                        ->where('car_parent_cache.parent_id IN (?)', $carIds)
                        ->where('pictures.status IN (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
                        ->order(array(
                            'car_parent_cache.sport', 'car_parent_cache.tuning',
                            'perspectives_groups_perspectives.position',
                            'width DESC', 'height DESC'
                        ))
                        ->limit(1)
                );

                $list[] = $picture ? $picture : null;
            }

            $ids = array();
            foreach ($list as $picture) {
                if ($picture) {
                    $ids[] = $picture->id;
                }
            }

            $pictureList = array();
            foreach ($list as $picture) {
                $pic = $picture;
                if (!$pic) {
                    $select = $pictures->select(true)
                        ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                        ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                        ->where('car_parent_cache.parent_id IN (?)', $carIds)
                        ->where('pictures.status IN (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
                        ->order(array(
                            'car_parent_cache.sport', 'car_parent_cache.tuning',
                            'pictures.width DESC', 'pictures.height DESC'
                        ))
                        ->limit(1);

                    if (count($ids) > 0) {
                        if (count($ids) > 1) {
                            $select->where('pictures.id NOT IN (?)', $ids);
                        } else {
                            $select->where('pictures.id <> ?', $ids[0]);
                        }
                    }

                    $pic = $pictures->fetchRow($select);
                    if ($pic) {
                        $ids[] = $pic->id;
                    }
                }
                $pictureList[] = $pic;
            }
            $list = $pictureList;
        }

        return $list;
    }
}