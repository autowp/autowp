<?php

namespace Application\Model;

use Application\Model\DbTable\Vehicle;

class Item
{
    public function getEngineVehiclesGroups($engineId, array $options = [])
    {
        $defaults = [
            'groupJoinLimit' => null
        ];
        $options = array_replace($defaults, $options);

        $itemTable = new Vehicle();

        $db = $itemTable->getAdapter();

        $vehicleIds = $db->fetchCol(
            $db->select()
                ->from('cars', 'id')
                ->join('item_parent_cache', 'cars.engine_item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $engineId)
        );

        $vectors = [];
        foreach ($vehicleIds as $vehicleId) {
            $parentIds = $db->fetchCol(
                $db->select()
                    ->from('item_parent_cache', 'parent_id')
                    ->where('item_id = ?', $vehicleId)
                    ->where('item_id <> parent_id')
                    ->order('diff desc')
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
}
