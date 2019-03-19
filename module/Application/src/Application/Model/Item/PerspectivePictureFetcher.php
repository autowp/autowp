<?php

namespace Application\Model\Item;

use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;

class PerspectivePictureFetcher extends PictureFetcher
{
    private $perspectivePageId = null;

    private $perspectiveCache = [];

    private $onlyExactlyPictures = false;

    private $type = null;

    private $onlyChilds = [];

    private $disableLargePictures = false;

    /**
     * @var Perspective
     */
    private $perspective;

    private function getPerspectiveGroupIds(int $pageId): array
    {
        if (isset($this->perspectiveCache[$pageId])) {
            return $this->perspectiveCache[$pageId];
        }

        $ids = $this->perspective->getPageGroupIds($pageId);

        $this->perspectiveCache[$pageId] = $ids;

        return $ids;
    }

    public function setPerspective(Perspective $model)
    {
        $this->perspective = $model;

        return $this;
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

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function fetch($item, array $options = [])
    {
        $pictures = [];
        $usedIds = [];

        $totalPictures = isset($options['totalPictures']) ? (int)$options['totalPictures'] : null;
        $itemOnlyChilds = isset($this->onlyChilds[$item['id']]) ? $this->onlyChilds[$item['id']] : null;

        $pPageId = null;
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

            $select->limit(1);

            $picture = $this->pictureModel->getTable()->selectWith($select)->current();

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

            $select->limit($needMore);

            $rows = $this->pictureModel->getTable()->selectWith($select);

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
                $result[] = [
                    'row' => $picture,
                ];
            } else {
                $result[] = false;
                $emptyPictures++;
            }
        }

        if ($emptyPictures > 0 && ($item['item_type_id'] == Item::ENGINE)) {
            $pictureRows = $this->pictureModel->getRows([
                'status' => Picture::STATUS_ACCEPTED,
                'item'   => [
                    'perspective' => 17,
                    'engine'      => [
                        'ancestor_or_self' => $item['id']
                    ]
                ],
                'limit'  => $emptyPictures
            ]);

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
                    'row'           => $pictureRow,
                    'isVehicleHood' => true
                ];
            }
        }

        return $result;
    }
}
