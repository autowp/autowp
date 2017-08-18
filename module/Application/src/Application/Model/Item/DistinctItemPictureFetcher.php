<?php

namespace Application\Model\Item;

use Application\Model\Item;

class DistinctItemPictureFetcher extends PictureFetcher
{
    const PERSPECTIVE_GROUP_ID = 31;
    const COUNT = 4;

    public function fetch($item, array $options = [])
    {

        $ids = $this->itemModel->getIds([
            'item_type_id'     => Item::CATEGORY,
            'ancestor_or_self' => $item['id'],
            'limit'            => self::COUNT
        ]);

        if (! $ids) {
            return [];
        }

        $result = [];
        $usedIds = [];
        for ($idx = 0; $idx < self::COUNT; $idx++) {
            $itemId = $ids[$idx % count($ids)];

            $select = $this->getPictureSelect($itemId, [
                'perspectiveGroup' => self::PERSPECTIVE_GROUP_ID,
                'exclude'          => $usedIds,
                'dateSort'         => $this->dateSort,
            ]);

            $picture = $db->fetchRow($select);

            if ($picture) {
                $usedIds[] = $picture['id'];

                $result[] = [
                    'format' => 'picture-thumb',
                    'row'    => $picture,
                ];
            } else {
                $result[] = false;
            }
        }

        return $result;
    }
}
