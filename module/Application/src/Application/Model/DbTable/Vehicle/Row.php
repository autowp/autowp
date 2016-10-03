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

        if (!is_null($by)) {
            if ($by > 0) {
                $result = $by;
                if (!is_null($ey) && ($ey > $by)) {
                    if ( ((int)($ey/1000)) == ((int)($by/1000)) ) {
                        $result .= '–'.substr($ey, 2, 2);
                    } else {
                        $result .= '–'.$ey;
                    }
                } elseif (is_null($ey) || ($ey <= 0) || ($ey < $by)) {
                    if ($options['today']) {
                        if ($by < $cy) {
                            $result .= '–н.в.';
                        }
                    }
                    elseif ($by < $cy) {
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
        if (!is_string($language)) {
            throw new Exception('`language` is not string');
        }

        $carLangTable = new VehicleLanguage();
        $carLangRow = $carLangTable->fetchRow([
            'car_id = ?'   => $this->id,
            'language = ?' => (string)$language
        ]);

        $name = $carLangRow ? $carLangRow->name : $this->caption;

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

    public function getFullName($language)
    {
        return self::buildFullName($this->getNameData($language));
    }

    public function getYearsString()
    {
        return self::buildYearsString([
            'begin_year' => $this->begin_year,
            'end_year'   => $this->end_year,
            'today'      => $this->today
        ]);
    }

    public function getCaptionHtml()
    {
        $result = htmlspecialchars($this->caption);

        if (strlen($this->body) > 0) {
            $result .= ' ('.htmlspecialchars($this->body).')';
        }

        $by = $this->begin_year;
        $bm = $this->begin_month;
        $ey = $this->end_year;
        $em = $this->end_month;
        $cy = (int)date('Y');

        $bs = (int)($by/100);
        $es = (int)($ey/100);

        $equalS = $bs && $es && ($bs == $es);
        $equalY = $equalS && $by && $ey && ($by == $ey);
        $equalM = $equalY && $bm && $em && ($bm == $em);


        if ($by > 0 || $ey > 0)
        {
          $result .= " '";

          if ($equalM)
          {
            $result .= sprintf('<span class="month">%02d.</span>', $bm).$by;
          }
          else
          {
            if ($equalY) {
                if ($bm && $em) {
                    $result .= '<span class="month">'.($bm ? sprintf('%02d', $bm) : '??').'–'.($em ? sprintf('%02d', $em) : '??').'.</span>'.$by;
                } else {
                    $result .= $by;
                }
            }
            else
            {
              if ($equalS)
              {
                $result .= (($bm ? sprintf('<span class="month">%02d.</span>', $bm) : '').$by).
                           '–'.
                           ($em ? sprintf('<span class="month">%02d.</span>', $em) : '').($em ? $ey : sprintf('%02d', $ey%100));
              }
              else
              {
                $result .= (($bm ? sprintf('<span class="month">%02d.</span>', $bm) : '').($by ? $by : '????')).
                           (
                             $ey
                             ?
                               '–'.($em ? sprintf('<span class="month">%02d.</span>', $em) : '').$ey
                             :
                               (
                                 $this->today
                                 ?
                                   ($by < $cy ? '–н.в.' : '')
                                 :
                                   ($by < $cy ? '–????' : '')
                               )
                           );
              }
            }
          }
        }

        return $result;
    }

    public function getOrientedPictureList(array $perspectiveGroupIds)
    {
        $pictureTable = new Picture();
        $pictures = [];
        $db = $this->getTable()->getAdapter();

        foreach ($perspectiveGroupIds as $groupId) {
            $picture = $pictureTable->fetchRow(
                $pictureTable->select(true)
                    ->where('pictures.type = ?', Picture::VEHICLE_TYPE_ID)
                    ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                    ->join(['mp' => 'perspectives_groups_perspectives'], 'pictures.perspective_id=mp.perspective_id', null)
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
        foreach ($pictures as $picture)
            if ($picture)
                $ids[] = $picture->id;

        foreach ($pictures as $key => $picture)
        {
            if (!$picture)
            {
                $select = $pictureTable->select(true)
                    ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                    ->where('pictures.type=?', Picture::VEHICLE_TYPE_ID)
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

        $sql = 'SELECT COUNT(pictures.id) '.
               'FROM pictures WHERE pictures.car_id=? AND pictures.type=?';
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

    public function getAttrsZone()
    {
        $id = 1;
        switch ($this->car_type_id) {
            case 19:
            case 28:
            case 32:
                $id = 3;
                break;
            case 17:
                $id = 2;
                break;
        }
        $zones = new Attr\Zone();
        return $zones->find($id)->current();
    }

    public function getEquipesIds()
    {
        $result = [];
        foreach ($this->findEquipes() as $equipe) {
            $result[] = $equipe->id;
        }
        return $result;
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
                $this->begin_model_year-1,
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
              'end_order_cache' =>   $end ?   $end->format(MYSQL_DATETIME_FORMAT) : null,
        ]);
        $this->save();
    }
}