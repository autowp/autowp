<?php

namespace Application\Model;

use Application\Model\DbTable;

use Zend_Db_Expr;

class Item
{
    public function getEngineVehiclesGroups($engineId, array $options = [])
    {
        $defaults = [
            'groupJoinLimit' => null
        ];
        $options = array_replace($defaults, $options);

        $itemTable = new DbTable\Item();

        $db = $itemTable->getAdapter();

        $vehicleIds = $db->fetchCol(
            $db->select()
                ->from('item', 'id')
                ->join('item_parent_cache', 'item.engine_item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $engineId)
        );

        $vectors = [];
        foreach ($vehicleIds as $vehicleId) {
            $parentIds = $db->fetchCol(
                $db->select()
                    ->from('item_parent_cache', 'parent_id')
                    ->join('item', 'item_parent_cache.parent_id = item.id', null)
                    ->where('item.item_type_id = ?', DbTable\Item\Type::VEHICLE)
                    ->where('item_parent_cache.item_id = ?', $vehicleId)
                    ->where('item_parent_cache.item_id <> item_parent_cache.parent_id')
                    ->order('item_parent_cache.diff desc')
            );

            // remove parents
            foreach ($parentIds as $parentId) {
                $index = array_search($parentId, $vehicleIds);
                if ($index !== false) {
                    unset($vehicleIds[$index]);
                }
            }

            $vector = $parentIds;
            $vector[] = $vehicleId;

            $vectors[] = $vector;
        }

        if ($options['groupJoinLimit'] && count($vehicleIds) <= $options['groupJoinLimit']) {
            return $vehicleIds;
        }


        do {
            // look for same root

            $matched = false;
            for ($i = 0; ($i < count($vectors) - 1) && ! $matched; $i++) {
                for ($j = $i + 1; $j < count($vectors) && ! $matched; $j++) {
                    if ($vectors[$i][0] == $vectors[$j][0]) {
                        $matched = true;
                        // matched root
                        $newVector = [];
                        $length = min(count($vectors[$i]), count($vectors[$j]));
                        for ($k = 0; $k < $length && $vectors[$i][$k] == $vectors[$j][$k]; $k++) {
                            $newVector[] = $vectors[$i][$k];
                        }
                        $vectors[$i] = $newVector;
                        array_splice($vectors, $j, 1);
                    }
                }
            }
        } while ($matched);

        $resultIds = [];
        foreach ($vectors as $vector) {
            $resultIds[] = $vector[count($vector) - 1];
        }

        return $resultIds;
    }

    public function getName($itemId, $language)
    {
        $carLangTable = new DbTable\Item\Language();

        $db = $carLangTable->getAdapter();

        $languages = array_merge([$language], ['en', 'it', 'fr', 'de', 'es', 'pt', 'ru', 'zh', 'xx']);

        $select = $db->select()
            ->from('item_language', ['name'])
            ->where('item_id = ?', (int)$itemId)
            ->where('length(name) > 0')
            ->order(new Zend_Db_Expr($db->quoteInto('FIELD(language, ?)', $languages)))
            ->limit(1);

        return $db->fetchOne($select);
    }
}
