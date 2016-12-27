<?php

namespace Application\Most\Adapter;

use Application\Model\DbTable\Attr\Attribute;

use Zend_Db_Expr;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;
use Zend_Db_Table_Select;

class Power extends AbstractAdapter
{
    protected $attribute;

    protected $order;

    const TEMP_TABLE_NAME = '__engine_power_temp';

    public function setAttributes(array $value)
    {
        $this->attributes = $value;
    }

    public function setOrder($value)
    {
        $this->order = $value;
    }

    public function getCars(Zend_Db_Table_Select $select, $language)
    {
        $carsTable = $select->getTable();
        $db = $carsTable->getAdapter();

        $wheres = implode($select->getPart(Zend_Db_Select::WHERE));
        $joins = $select->getPart(Zend_Db_Select::FROM);
        unset($joins['cars']);

        $tableNameQuoted = $db->quoteIdentifier(self::TEMP_TABLE_NAME);

        $db->query('
            create temporary table '.$tableNameQuoted.' (
                item_id int unsigned not null,
                power int unsigned not null,
                primary key(item_id)
            )
        ');

        $limit = $this->most->getCarsCount();



        $attributes = new Attribute();

        $powerAttr = $attributes->find($this->attributes['power'])->current();

        $specService = $this->most->getSpecs();

        $valuesTable = $specService->getValueDataTable($powerAttr->type_id);
        $valuesTableName = $valuesTable->info(Zend_Db_Table_Abstract::NAME);



        $funct = $this->order == 'ASC' ? 'min' : 'max';
        $expr = $funct.'('.$valuesTableName.'.value)';
        $attrsSelect = $db->select()
            ->from(['engines' => 'cars'], ['cars.id', 'V' => new Zend_Db_Expr($expr)])
            ->join('cars', 'engines.id = cars.engine_item_id', null)
            ->join($valuesTableName, 'engines.id = '.$valuesTableName.'.item_id', null)
            ->where($valuesTableName.'.attribute_id = ?', $powerAttr->id)
            ->where($valuesTableName.'.value > 0')
            ->group('cars.id')
            ->order('V '. $this->order)
            ->limit($limit);

        if ($wheres) {
            $attrsSelect->where($wheres);
        }
        foreach ($joins as $join) {
            if ($join['joinType'] == Zend_Db_Select::INNER_JOIN) {
                $attrsSelect->join($join['tableName'], $join['joinCondition'], null, $join['schema']);
            }
        }

        $db->query(
            'insert ignore into '.$tableNameQuoted.' (item_id, power) '.
            $attrsSelect->assemble()
        );

        //print $attrsSelect->assemble();


        $funct = $this->order == 'ASC' ? 'min' : 'max';
           $expr = $funct.'('.$valuesTableName.'.value)';
        $attrsSelect = $db->select()
            ->from('cars', ['cars.id', 'V' => new Zend_Db_Expr($expr)])
            ->join($valuesTableName, 'cars.id='.$valuesTableName.'.item_id', null)
            ->where($valuesTableName.'.attribute_id = ?', $powerAttr->id)
            ->where($valuesTableName.'.value > 0')
            ->group('cars.id')
            ->order('V '. $this->order)
            ->limit($limit);

        if ($wheres) {
            $attrsSelect->where($wheres);
        }
        foreach ($joins as $join) {
            if ($join['joinType'] == Zend_Db_Select::INNER_JOIN) {
                $attrsSelect->join($join['tableName'], $join['joinCondition'], null, $join['schema']);
            }
        }

        $db->query(
            'insert ignore into '.$tableNameQuoted.' (item_id, power) '.
            $attrsSelect->assemble()
        );


        $cars = $carsTable->fetchAll(
            $select
                ->join(self::TEMP_TABLE_NAME, 'cars.id='.$tableNameQuoted.'.item_id', null)
                ->group('cars.id')
                ->order('power ' . $this->order)
        );

        $result = [];
        foreach ($cars as $car) {
            $html = '';
            $value = $specService->getActualValue($powerAttr->id, $car->id);
            $turbo = $specService->getActualValueText($this->attributes['turbo'], $car->id, $language);
            switch ($turbo) {
                case 'нет':
                    $turbo = null;
                    break;
                case 'есть':
                    $turbo = 'турбонаддув';
                    break;
                default:
                    if ($turbo) {
                        $turbo = 'турбонаддув ' . $turbo;
                    }
                    break;
            }
            $volume = $specService->getActualValue($this->attributes['volume'], $car->id);
            $cylindersLayout = $specService->getActualValueText(
                $this->attributes['cylindersLayout'],
                1,
                $car->id,
                $language
            );
            $cylindersCount = $specService->getActualValue($this->attributes['cylindersCount'], $car->id);
            $valvePerCylinder = $specService->getActualValue($this->attributes['valvePerCylinder'], $car->id);

            $cyl = $this->cylinders($cylindersLayout, $cylindersCount, $valvePerCylinder);


            $html .= $value;
            $html .= ' <span class="unit">л.с.</span>';


            $engText = '';
            if (strlen($cyl) || $turbo || $volume) {
                $a = [];

                if ($cyl) {
                    $a[] = htmlspecialchars($cyl);
                }

                if ($volume > 0) {
                    $a[] = sprintf('%0.1f <span class="unit">л</span>', $volume / 1000);
                }

                if ($turbo) {
                    $a[] = $turbo;
                }

                $html .= '<p class="note">'.implode(', ', $a).'</p>';
            }

            $result[] = [
                'car'         => $car,
                'valueHtml'    => $html,
            ];
        }

        return [
            'unit' => null,//$attribute->findParentAttrs_Units(),
            'cars' => $result,
        ];
    }

    protected function cylinders($layout, $cylinders, $valve_per_cylinder = null)
    {
        if ($layout) {
            if ($cylinders) {
                $result = $layout.$cylinders;
            } else {
                $result = $layout.'?';
            }
        } else {
            if ($cylinders) {
                $result = $cylinders;
            } else {
                $result = '';
            }
        }

        if ($valve_per_cylinder) {
            $result .= '/' . $valve_per_cylinder;
        }

        return $result;
    }
}
