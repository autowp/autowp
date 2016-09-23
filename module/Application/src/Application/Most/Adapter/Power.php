<?php

namespace Applicaton\Most\Adapter;

use Attrs_Attributes;
use Attrs_Item_Types;

use Zend_Db_Expr;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;
use Zend_Db_Table_Select;

class Power extends AbstractAdapter
{
    protected $_attribute;

    protected $_order;

    const TEMP_TABLE_NAME = '__engine_power_temp';

    public function setAttributes(array $value)
    {
        $this->_attributes = $value;
    }

    public function setOrder($value)
    {
        $this->_order = $value;
    }

    public function getCars(Zend_Db_Table_Select $select, $language)
    {
        $carsTable = $select->getTable();
        $db = $carsTable->getAdapter();

        $wheres = implode($select->getPart( Zend_Db_Select::WHERE ));
        $joins = $select->getPart( Zend_Db_Select::FROM );
        unset($joins['cars']);

        $tableNameQuoted = $db->quoteIdentifier(self::TEMP_TABLE_NAME);

        $db->query('
            create temporary table '.$tableNameQuoted.' (
                car_id int unsigned not null,
                power int unsigned not null,
                primary key(car_id)
            )
        ');

        $limit = $this->most->getCarsCount();



        $attributes = new Attrs_Attributes();
        $itemTypes = new Attrs_Item_Types();
        $carItemType = $itemTypes->find(1)->current();
        $engineItemType = $itemTypes->find(3)->current();

        $powerAttr = $attributes->find($this->_attributes['power'])->current();

        $specService = $this->most->getSpecs();

        $valuesTable = $specService->getValueDataTable($powerAttr->type_id);
        $valuesTableName = $valuesTable->info(Zend_Db_Table_Abstract::NAME);



        $funct = $this->_order == 'ASC' ? 'min' : 'max';
        $expr = $funct.'('.$valuesTableName.'.value)';
        $attrsSelect = $db->select()
            ->from('engines', ['cars.id', 'V' => new Zend_Db_Expr($expr)])
            ->join('cars', 'engines.id = cars.engine_id', null)
            ->join($valuesTableName, 'engines.id='.$valuesTableName.'.item_id', null)
            ->where($valuesTableName.'.item_type_id = ?', $engineItemType->id)
            ->where($valuesTableName.'.attribute_id = ?', $powerAttr->id)
            ->where($valuesTableName.'.value > 0')
            ->group('cars.id')
            ->order('V '. $this->_order)
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
            'insert ignore into '.$tableNameQuoted.' (car_id, power) '.
            $attrsSelect->assemble()
        );

        //print $attrsSelect->assemble();


        $funct = $this->_order == 'ASC' ? 'min' : 'max';
           $expr = $funct.'('.$valuesTableName.'.value)';
        $attrsSelect = $db->select()
            ->from('cars', ['cars.id', 'V' => new Zend_Db_Expr($expr)])
            ->join($valuesTableName, 'cars.id='.$valuesTableName.'.item_id', null)
            ->where($valuesTableName.'.item_type_id = ?', $carItemType->id)
            ->where($valuesTableName.'.attribute_id = ?', $powerAttr->id)
            ->where($valuesTableName.'.value > 0')
            ->group('cars.id')
            ->order('V '. $this->_order)
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
            'insert ignore into '.$tableNameQuoted.' (car_id, power) '.
            $attrsSelect->assemble()
        );


        $cars = $carsTable->fetchAll(
            $select
                ->join(self::TEMP_TABLE_NAME, 'cars.id='.$tableNameQuoted.'.car_id', null)
                ->group('cars.id')
                ->order('power ' . $this->_order)
        );

        $result = [];
        foreach ($cars as $car) {

            $html = '';
            $value = $specService->getActualValue($powerAttr->id, $car->id, 1);
            $turbo = $specService->getActualValueText($this->_attributes['turbo'], 1, $car->id, $language);
            switch ($turbo) {
                case 'нет': $turbo = null; break;
                case 'есть': $turbo = 'турбонаддув'; break;
                default:
                    if ($turbo) {
                        $turbo = 'турбонаддув ' . $turbo;
                    }
                    break;
            }
            $volume = $specService->getActualValue($this->_attributes['volume'], $car->id, 1);
            $cylindersLayout = $specService->getActualValueText($this->_attributes['cylindersLayout'], 1, $car->id, $language);
            $cylindersCount = $specService->getActualValue($this->_attributes['cylindersCount'], $car->id, 1);
            $valvePerCylinder = $specService->getActualValue($this->_attributes['valvePerCylinder'], $car->id, 1);

            $cyl = $this->_cylinders($cylindersLayout, $cylindersCount, $valvePerCylinder);


            $html .= $value;
            $html .= ' <span class="unit">л.с.</span>';


            $engText = '';
            if (strlen($cyl) || $turbo || $volume) {
                $a = [];

                if ($cyl) {
                    $a[] = htmlspecialchars($cyl);
                }

                if ($volume > 0)
                    $a[] = sprintf('%0.1f <span class="unit">л</span>', $volume/1000);

                if ($turbo) {
                    $a[] = $turbo;
                }

                $html .= '<p class="note">'.implode(', ', $a).'</p>';
            }

            $result[] = [
                'car'         =>  $car,
                'valueHtml'    => $html,
            ];
        }

        return [
            'unit' => null,//$attribute->findParentAttrs_Units(),
            'cars' => $result,
        ];
    }

    protected function _cylinders($layout, $cylinders, $valve_per_cylinder = null)
    {
        if ($layout) {
            if ($cylinders)
                $result = $layout.$cylinders;
            else
                $result = $layout.'?';
        } else {
            if ($cylinders)
                $result = $cylinders;
            else
                $result = '';
        }

        if ($valve_per_cylinder)
            $result .= '/' . $valve_per_cylinder;

        return $result;
    }
}