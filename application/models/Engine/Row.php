<?php

use Application\Db\Table\Row;

class Engine_Row extends Row
{
    public function getRelatedCarGroupId(array $options = [])
    {
        $defaults = [
            'groupJoinLimit' => null
        ];
        $options = array_merge($defaults, $options);

        $carTable = new Cars();

        $db = $this->getTable()->getAdapter();

        $carIds = $db->fetchCol(
            $db->select()
                ->from('cars', 'id')
                ->join('engine_parent_cache', 'cars.engine_id = engine_parent_cache.engine_id', null)
                ->where('engine_parent_cache.parent_id = ?', $this->id)
        );

        $vectors = array();
        foreach ($carIds as $carId) {
            $parentIds = $db->fetchCol(
                $db->select()
                    ->from('car_parent_cache', 'parent_id')
                    ->where('car_id = ?', $carId)
                    ->where('car_id <> parent_id')
                    ->order('diff desc')
            );

            // remove parents
            foreach ($parentIds as $parentId) {
                $index = array_search($parentId, $carIds);
                if ($index !== false) {
                    unset($carIds[$index]);
                }
            }

            $vector = $parentIds;
            $vector[] = $carId;

            $vectors[] = $vector;
        }

        if ($options['groupJoinLimit'] && count($carIds) <= $options['groupJoinLimit']) {
            return $carIds;
        }


        do {
            // look for same root

            $matched = false;
            for ($i=0; ($i<count($vectors)-1) && !$matched; $i++) {
                for ($j=$i+1; $j<count($vectors) && !$matched; $j++) {
                    if ($vectors[$i][0] == $vectors[$j][0]) {
                        $matched = true;
                        // matched root
                        $newVector = array();
                        $length = min(count($vectors[$i]), count($vectors[$j]));
                        for ($k=0; $k<$length && $vectors[$i][$k] == $vectors[$j][$k]; $k++) {
                            $newVector[] = $vectors[$i][$k];
                        }
                        $vectors[$i] = $newVector;
                        array_splice($vectors, $j, 1);
                    }
                }
            }

        } while($matched);

        $resultIds = array();
        foreach ($vectors as $vector) {
            $resultIds[] = $vector[count($vector)-1];
        }

        return $resultIds;
    }
}
