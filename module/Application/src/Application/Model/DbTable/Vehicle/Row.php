<?php

namespace Application\Model\DbTable\Vehicle;

use Application\Model\Brand as BrandModel;
use Application\Model\DbTable\Attr;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Vehicle\Language as VehicleLanguage;
use Application\Model\DbTable\Spec;

use DateTime;
use Exception;

use Zend_Db_Expr;

class Row extends \Application\Db\Table\Row
{
    /**
     * @var Spec
     */
    private $specTable;

    /**
     * @return Spec
     */
    private function getSpecTable()
    {
        return $this->specTable
            ? $this->specTable
            : $this->specTable = new Spec();
    }

    private static function buildYearsString(array $options)
    {
        $defaults = [
            'begin_year' => null,
            'end_year'   => null,
            'today'      => null
        ];
        $options = array_replace($defaults, $options);

        $result = '';

        $by = $options['begin_year'];
        $ey = $options['end_year'];

        $cy = (int)date('Y');

        if (! is_null($by)) {
            if ($by > 0) {
                $result = $by;
                if (! is_null($ey) && ($ey > $by)) {
                    if (((int)($ey / 1000)) == ((int)($by / 1000))) {
                        $result .= '–'.substr($ey, 2, 2);
                    } else {
                        $result .= '–'.$ey;
                    }
                } elseif (is_null($ey) || ($ey <= 0) || ($ey < $by)) {
                    if ($options['today']) {
                        if ($by < $cy) {
                            $result .= '–н.в.';
                        }
                    } elseif ($by < $cy) {
                        $result .= '–????';
                    }
                }
            }
        }

        return $result;
    }

    public static function buildFullName(array $options)
    {
        $defaults = [
            'begin_model_year' => null,
            'end_model_year'   => null,
            'spec'             => null,
            'body'             => null,
            'name'             => null,
            'begin_year'       => null,
            'end_year'         => null,
            'today'            => null
        ];
        $options = array_replace($defaults, $options);

        $result = $options['name'];

        if ($options['spec']) {
            $result .= ' ' . $options['spec'];
        }

        $by = $options['begin_model_year'];
        $ey = $options['end_model_year'];

        if ($by) {
            if ($ey) {
                if ($by != $ey) {
                    $by10 = floor($by / 100);
                    $ey10 = floor($ey / 100);
                    if ($by10 != $ey10) {
                        $result = $by . '–' . $ey . ' ' . $result;
                    } else {
                        $result = $by . '–' . substr($ey, 2, 2) . ' ' . $result;
                    }
                } else {
                    $result = $by . ' ' . $result;
                }
            } else {
                $result = $by . ' ' . $result;
            }
        }

        if (strlen($options['body']) > 0) {
            $result .= ' ('.$options['body'].')';
        }

        $years = self::buildYearsString([
            'begin_year' => $options['begin_year'],
            'end_year'   => $options['end_year'],
            'today'      => $options['today']
        ]);
        if ($years) {
            $result .= " '".$years;
        }

        return $result;
    }

    public function getNameData($language = 'en')
    {
        if (! is_string($language)) {
            throw new Exception('`language` is not string');
        }

        $carLangTable = new VehicleLanguage();
        $carLangRow = $carLangTable->fetchRow([
            'car_id = ?'   => $this->id,
            'language = ?' => (string)$language
        ]);

        $name = $carLangRow ? $carLangRow->name : $this->name;

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
            'today'            => $this->today
        ];
    }

    public function getYearsString()
    {
        return self::buildYearsString([
            'begin_year' => $this->begin_year,
            'end_year'   => $this->end_year,
            'today'      => $this->today
        ]);
    }

    public function getOrientedPictureList(array $perspectiveGroupIds)
    {
        $pictureTable = new Picture();
        $pictures = [];
        $db = $this->getTable()->getAdapter();

        foreach ($perspectiveGroupIds as $groupId) {
            $picture = $pictureTable->fetchRow(
                $pictureTable->select(true)
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->join('car_parent_cache', 'picture_item.item_id = car_parent_cache.car_id', null)
                    ->join(
                        ['mp' => 'perspectives_groups_perspectives'],
                        'picture_item.perspective_id = mp.perspective_id',
                        null
                    )
                    ->where('mp.group_id=?', $groupId)
                    ->where('car_parent_cache.parent_id = ?', $this->id)
                    ->where('not car_parent_cache.sport and not car_parent_cache.tuning')
                    ->where('pictures.status IN (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
                    ->order([
                        'mp.position',
                        new Zend_Db_Expr($db->quoteInto('pictures.status=? DESC', Picture::STATUS_ACCEPTED)),
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
                    ->join('car_parent_cache', 'picture_item.item_id = car_parent_cache.car_id', null)
                    ->where('car_parent_cache.parent_id = ?', $this->id)
                    ->where('not car_parent_cache.sport and not car_parent_cache.tuning')
                    ->where('pictures.status IN (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
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
        $this->pictures_count = (int)$db->fetchOne($sql, [$this->id, Picture::VEHICLE_TYPE_ID]);
        $this->save();

        $brandModel = new BrandModel();
        $brandModel->refreshPicturesCountByVehicle($this->id);
    }

    public function deleteFromBrand(\Application\Model\DbTable\BrandRow $brand)
    {
        $db = $this->getTable()->getAdapter();
        $sql = 'DELETE FROM brands_cars WHERE (brand_id=?) AND (car_id=?) LIMIT 1';
        $db->query($sql, [$brand->id, $this->id]);

        $brand->refreshPicturesCount();
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
