<?php

namespace Application\Model\Item;

use Application\Model\DbTable;

class DistinctItemPictureFetcher extends PictureFetcher
{
    const PERSPECTIVE_GROUP_ID = 31;
    const COUNT = 4;
    
    public function fetch(array $item, array $options = [])
    {
        $pictureTable = $this->getPictureTable();
        $db = $pictureTable->getAdapter();
        
        $ids = $db->fetchCol(
            $db->select()
                ->from('cars', 'id')
                ->where('cars.item_type_id <> ?', DbTable\Item\Type::CATEGORY)
                ->join('item_parent_cache', 'cars.id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $item['id'])
                ->limit(self::COUNT)
        );
        
        if (!$ids) {
            return [];
        }
        
        $result = [];
        $usedIds = [];
        for ($idx=0; $idx<self::COUNT; $idx++) {
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
