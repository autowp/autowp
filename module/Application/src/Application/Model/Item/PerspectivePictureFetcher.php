<?php

namespace Application\Model\Item;

use Application\Model\DbTable;

class PerspectivePictureFetcher extends PictureFetcher
{
    private $perspectivePageId = null;

    private $perspectiveCache = [];

    private $onlyExactlyPictures = false;

    private $type = null;

    private $onlyChilds = [];

    private $disableLargePictures = false;

    private function getPerspectiveGroupIds($pageId)
    {
        if (isset($this->perspectiveCache[$pageId])) {
            return $this->perspectiveCache[$pageId];
        }

        $perspectivesGroups = new DbTable\Perspective\Group();
        $db = $perspectivesGroups->getAdapter();
        $ids = $db->fetchCol(
            $db->select()
                ->from($perspectivesGroups->info('name'), 'id')
                ->where('page_id = ?', $pageId)
                ->order('position')
        );

        $this->perspectiveCache[$pageId] = $ids;

        return $ids;
    }

    public function setPerspectivePageId($id)
    {
        $this->perspectivePageId = (int)$id;

        return $this;
    }

    public function setOnlyExactlyPictures($value)
    {
        $this->onlyExactlyPictures = (bool)$value;

        return $this;
    }

    public function setType($value)
    {
        $this->type = (bool)$value;

        return $this;
    }

    public function setOnlyChilds(array $onlyChilds)
    {
        $this->onlyChilds = $onlyChilds;

        return $this;
    }

    public function setDisableLargePictures($value)
    {
        $this->disableLargePictures = (bool)$value;

        return $this;
    }

    public function fetch(array $item, array $options = [])
    {
        $pictures = [];
        $usedIds = [];

        $pictureTable = $this->getPictureTable();
        $db = $pictureTable->getAdapter();

        $totalPictures = isset($options['totalPictures']) ? (int)$options['totalPictures'] : null;
        $itemOnlyChilds = isset($this->onlyChilds[$item['id']]) ? $this->onlyChilds[$item['id']] : null;

        $pPageId = null;
        $useLargeFormat = false;
        if ($this->perspectivePageId) {
            $pPageId = $this->perspectivePageId;
        } else {
            $useLargeFormat = $totalPictures > 30 && ! $this->disableLargePictures;
            $pPageId = $useLargeFormat ? 5 : 4;
        }

        $perspectiveGroupIds = $this->getPerspectiveGroupIds($pPageId);

        foreach ($perspectiveGroupIds as $groupId) {
            $select = $this->getPictureSelect($item['id'], [
                'onlyExactlyPictures' => $this->onlyExactlyPictures,
                'perspectiveGroup'    => $groupId,
                'type'                => $this->type,
                'exclude'             => $usedIds,
                'dateSort'            => $this->dateSort,
                'onlyChilds'          => $itemOnlyChilds
            ]);

            $picture = $db->fetchRow($select);

            if ($picture) {
                $pictures[] = $picture;
                $usedIds[] = (int)$picture['id'];
            } else {
                $pictures[] = null;
            }
        }

        $needMore = count($perspectiveGroupIds) - count($usedIds);

        if ($needMore > 0) {
            $select = $this->getPictureSelect($item['id'], [
                'onlyExactlyPictures' => $this->onlyExactlyPictures,
                'type'                => $this->type,
                'exclude'             => $usedIds,
                'dateSort'            => $this->dateSort,
                'onlyChilds'          => $itemOnlyChilds
            ]);

            $rows = $db->fetchAll(
                $select->limit($needMore)
            );
            $morePictures = [];
            foreach ($rows as $row) {
                $morePictures[] = $row;
            }

            foreach ($pictures as $key => $picture) {
                if (count($morePictures) <= 0) {
                    break;
                }
                if (! $picture) {
                    $pictures[$key] = array_shift($morePictures);
                }
            }
        }

        $result = [];
        $emptyPictures = 0;
        foreach ($pictures as $idx => $picture) {
            if ($picture) {
                $pictureId = $picture['id'];

                $format = $useLargeFormat && $idx == 0 ? 'picture-thumb-medium' : 'picture-thumb';

                $result[] = [
                    'format' => $format,
                    'row'    => $picture,
                ];
            } else {
                $result[] = false;
                $emptyPictures++;
            }
        }

        if ($emptyPictures > 0 && ($item['item_type_id'] == DbTable\Item\Type::ENGINE)) {
            $pictureRows = $db->fetchAll(
                $db->select()
                    ->from('pictures', [
                        'id', 'name', 'type',
                        'image_id', 'crop_left', 'crop_top',
                        'crop_width', 'crop_height', 'width', 'height', 'identity'
                    ])
                    ->where('pictures.status IN (?)', [
                        DbTable\Picture::STATUS_NEW, DbTable\Picture::STATUS_ACCEPTED
                    ])
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->where('picture_item.perspective_id = ?', 17) // under the hood
                    ->join('item', 'picture_item.item_id = item.id', null)
                    ->join('item_parent_cache', 'item.engine_item_id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $item['id'])
                    ->limit($emptyPictures)
            );

            $extraPicIdx = 0;

            foreach ($result as $idx => $picture) {
                if ($picture) {
                    continue;
                }
                if (count($pictureRows) <= $extraPicIdx) {
                    break;
                }
                $pictureRow = $pictureRows[$extraPicIdx++];
                $result[$idx] = [
                    'format' => 'picture-thumb',
                    'row'    => $pictureRow,
                    'isVehicleHood' => true
                ];
            }
        }

        return $result;
    }
}
