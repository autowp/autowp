<?php

namespace Application\Model\DbTable\Item;

use Application\Model\DbTable;
use Application\Model\Item as ItemModel;

use DateTime;
use Exception;

use Zend_Db_Expr;

class Row extends \Autowp\Commons\Db\Table\Row
{
    /**
     * @var DbTable\Spec
     */
    private $specTable;

    /**
     * @return DbTable\Spec
     */
    private function getSpecTable()
    {
        return $this->specTable
            ? $this->specTable
            : $this->specTable = new DbTable\Spec();
    }

    public function getNameData($language = 'en')
    {
        if (! is_string($language)) {
            throw new Exception('`language` is not string');
        }

        /*$carLangTable = new DbTable\Item\Language();
        $carLangRow = $carLangTable->fetchRow([
            'item_id = ?'  => $this->id,
            'language = ?' => (string)$language
        ]);

        $name = $carLangRow && $carLangRow->name ? $carLangRow->name : $this->name;*/

        $itemModel = new ItemModel();
        $name = $itemModel->getName($this['id'], $language);

        $spec = null;
        $specFull = null;
        if ($this->spec_id) {
            $specRow = $this->getSpecTable()->find($this->spec_id)->current();
            if ($specRow) {
                $spec = $specRow->short_name;
                $specFull = $specRow->name;
            }
        }

        return [
            'begin_model_year' => $this->begin_model_year,
            'end_model_year'   => $this->end_model_year,
            'spec'             => $spec,
            'spec_full'        => $specFull,
            'body'             => $this->body,
            'name'             => $name,
            'begin_year'       => $this->begin_year,
            'end_year'         => $this->end_year,
            'today'            => $this->today,
            'begin_month'      => $this->begin_month,
            'end_month'        => $this->end_month,
        ];
    }

    public function getOrientedPictureList(array $perspectiveGroupIds)
    {
        $pictureTable = new DbTable\Picture();
        $pictures = [];
        $db = $this->getTable()->getAdapter();

        foreach ($perspectiveGroupIds as $groupId) {
            $picture = $pictureTable->fetchRow(
                $pictureTable->select(true)
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->join(
                        ['mp' => 'perspectives_groups_perspectives'],
                        'picture_item.perspective_id = mp.perspective_id',
                        null
                    )
                    ->where('mp.group_id=?', $groupId)
                    ->where('item_parent_cache.parent_id = ?', $this->id)
                    ->where('not item_parent_cache.sport and not item_parent_cache.tuning')
                    ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
                    ->order([
                        'mp.position',
                        new Zend_Db_Expr($db->quoteInto('pictures.status=? DESC', DbTable\Picture::STATUS_ACCEPTED)),
                        'pictures.width DESC', 'pictures.height DESC'
                    ])
                    ->limit(1)
            );

            if ($picture) {
                $pictures[] = $picture;
            } else {
                $pictures[] = null;
            }
        }

        $ids = [];
        foreach ($pictures as $picture) {
            if ($picture) {
                $ids[] = $picture->id;
            }
        }

        foreach ($pictures as $key => $picture) {
            if (! $picture) {
                $select = $pictureTable->select(true)
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $this->id)
                    ->where('not item_parent_cache.sport and not item_parent_cache.tuning')
                    ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
                    ->limit(1);

                if (count($ids) > 0) {
                    $select->where('id NOT IN (?)', $ids);
                }

                $pic = $pictureTable->fetchAll($select)->current();

                if ($pic) {
                    $pictures[$key] = $pic;
                    $ids[] = $pic->id;
                } else {
                    break;
                }
            }
        }

        return $pictures;
    }

    public function updateOrderCache()
    {
        $begin = null;
        if ($this->begin_year) {
            $begin = new DateTime();
            $begin->setDate(
                $this->begin_year,
                $this->begin_month ? $this->begin_month : 1,
                1
            );
        } elseif ($this->begin_model_year) {
            $begin = new DateTime();
            $begin->setDate( // approximation
                $this->begin_model_year - 1,
                10,
                1
            );
        } else {
            $begin = new DateTime();
            $begin->setDate(
                2100,
                1,
                1
            );
        }

        $end = null;
        if ($this->end_year) {
            $end = new DateTime();
            $end->setDate(
                $this->end_year,
                $this->end_month ? $this->end_month : 12,
                1
            );
        } elseif ($this->end_model_year) {
            $end = new DateTime();
            $end->setDate( // approximation
                $this->end_model_year,
                9,
                30
            );
        } else {
            $end = $begin;
        }

        $this->setFromArray([
            'begin_order_cache' => $begin ? $begin->format(MYSQL_DATETIME_FORMAT) : null,
              'end_order_cache' => $end ? $end->format(MYSQL_DATETIME_FORMAT) : null,
        ]);
        $this->save();
    }

    public function getRelatedCarGroupId()
    {
        $db = $this->getTable()->getAdapter();

        $carIds = $db->fetchCol(
            $db->select()
                ->from('item_parent', 'item_id')
                ->where('item_parent.parent_id = ?', $this->id)
        );

        $vectors = [];
        foreach ($carIds as $carId) {
            $parentIds = $db->fetchCol(
                $db->select()
                    ->from('item_parent_cache', 'parent_id')
                    ->join('item', 'item_parent_cache.parent_id = item.id', null)
                    ->where('item.item_type_id IN (?)', [
                        Type::VEHICLE,
                        Type::ENGINE
                    ])
                    ->where('item_id = ?', $carId)
                    ->where('item_id <> parent_id')
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

    public function getRelatedCarGroups()
    {
        $db = $this->getTable()->getAdapter();

        $carIds = $db->fetchCol(
            $db->select()
                ->from('item_parent', 'item_id')
                ->where('item_parent.parent_id = ?', $this->id)
        );

        $vectors = [];
        foreach ($carIds as $carId) {
            $parentIds = $db->fetchCol(
                $db->select()
                    ->from('item_parent_cache', 'parent_id')
                    ->join('item', 'item_parent_cache.parent_id = item.id', null)
                    ->where('item.item_type_id IN (?)', [
                        Type::VEHICLE,
                        Type::ENGINE
                    ])
                    ->where('item_parent_cache.item_id = ?', $carId)
                    ->where('item_parent_cache.item_id <> item_parent_cache.parent_id')
                    ->order('item_parent_cache.diff desc')
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

            $vectors[] = [
                'parents' => $vector,
                'childs'  => [$carId]
            ];
        }

        do {
            // look for same root

            $matched = false;
            for ($i = 0; ($i < count($vectors) - 1) && ! $matched; $i++) {
                for ($j = $i + 1; $j < count($vectors) && ! $matched; $j++) {
                    if ($vectors[$i]['parents'][0] == $vectors[$j]['parents'][0]) {
                        $matched = true;
                        // matched root
                        $newVector = [];
                        $length = min(count($vectors[$i]['parents']), count($vectors[$j]['parents']));
                        for ($k = 0; $k < $length && $vectors[$i]['parents'][$k] == $vectors[$j]['parents'][$k]; $k++) {
                            $newVector[] = $vectors[$i]['parents'][$k];
                        }
                        $vectors[$i] = [
                            'parents' => $newVector,
                            'childs'  => array_merge($vectors[$i]['childs'], $vectors[$j]['childs'])
                        ];
                        array_splice($vectors, $j, 1);
                    }
                }
            }
        } while ($matched);

        $result = [];
        foreach ($vectors as $vector) {
            $carId = $vector['parents'][count($vector['parents']) - 1];
            $result[$carId] = $vector['childs'];
        }

        return $result;
    }
}
