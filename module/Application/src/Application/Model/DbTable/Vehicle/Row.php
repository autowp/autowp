<?php

namespace Application\Model\DbTable\Vehicle;

use Application\Model\Brand as BrandModel;
use Application\Model\DbTable;

use DateTime;
use Exception;

use Zend_Db_Expr;

class Row extends \Application\Db\Table\Row
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

        $carLangTable = new DbTable\Vehicle\Language();
        $carLangRow = $carLangTable->fetchRow([
            'item_id = ?'  => $this->id,
            'language = ?' => (string)$language
        ]);

        $name = $carLangRow && $carLangRow->name ? $carLangRow->name : $this->name;

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
                    ->where('pictures.status IN (?)', [
                        DbTable\Picture::STATUS_ACCEPTED, 
                        DbTable\Picture::STATUS_NEW
                    ])
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
                    ->where('pictures.status IN (?)', [
                        DbTable\Picture::STATUS_ACCEPTED, 
                        DbTable\Picture::STATUS_NEW
                    ])
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

    public function refreshPicturesCount()
    {
        $db = $this->getTable()->getAdapter();

        $sql = '
            SELECT COUNT(pictures.id)
            FROM pictures
                JOIN picture_item ON pictures.id = picture_item.picture_id
            WHERE picture_item.item_id=? AND pictures.type=?
        ';
        $this->pictures_count = (int)$db->fetchOne($sql, [$this->id, DbTable\Picture::VEHICLE_TYPE_ID]);
        $this->save();

        $brandModel = new BrandModel();
        $brandModel->refreshPicturesCountByVehicle($this->id);
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
}
