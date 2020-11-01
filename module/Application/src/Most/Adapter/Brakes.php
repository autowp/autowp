<?php

namespace Application\Most\Adapter;

use ArrayAccess;
use Exception;
use Laminas\Db\Sql;

use function array_keys;
use function Autowp\Commons\currentFromResultSetInterface;
use function implode;

class Brakes extends AbstractAdapter
{
    protected array $attributes;

    protected string $order;

    public function setAttributes(array $value): void
    {
        /*$defaults = [
            'tyrewidth'  => null,
            'tyreseries' => null,
            'radius'  => null,
            'rimwidth'   => null
        ];*/
        $this->attributes = $value;
    }

    public function setOrder(string $value): void
    {
        $this->order = $value;
    }

    /**
     * @throws Exception
     */
    public function getCars(Sql\Select $select, string $language): array
    {
        $rear  = $this->attributes['rear'];
        $front = $this->attributes['front'];

        $wheres = $select->getRawState($select::WHERE);
        $joins  = $select->getRawState($select::JOINS)->getJoins();
        unset($joins['cars']);

        $limit = $this->most->getCarsCount();

        $specService = $this->most->getSpecs();

        $selects = [];
        foreach ([$rear, $front] as $axis) {
            $axisSelect = new Sql\Select('item');
            if ($wheres) {
                $axisSelect->where($wheres);
            }
            foreach ($joins as $join) {
                if ($join['type'] === Sql\Join::JOIN_INNER) {
                    $axisSelect->join($join['name'], $join['on'], $join['columns']);
                }
            }
            $axisSelect->reset($select::COLUMNS);

            $diameter            = currentFromResultSetInterface(
                $this->attributeTable->select(['id' => $axis['diameter']])
            );
            $diameterValuesTable = $specService->getValueDataTable($diameter['type_id'])->getTable();

            $thickness            = currentFromResultSetInterface(
                $this->attributeTable->select(['id' => $axis['thickness']])
            );
            $thicknessValuesTable = $specService->getValueDataTable($thickness['type_id'])->getTable();

            $axisSelect
                ->columns([
                    'item_id'    => 'item.id',
                    'size_value' => new Sql\Expression('diameter.value*thickness.value'),
                ])
                ->join(['diameter' => $diameterValuesTable], 'item.id = diameter.item_id', [])
                ->join(['thickness' => $thicknessValuesTable], 'item.id = thickness.item_id', [])
                ->where([
                    'diameter.attribute_id' => $diameter['id'],
                    'diameter.value > 0',
                    'thickness.attribute_id' => $thickness['id'],
                    'thickness.value > 0',
                ])
                ->group('item.id')
                ->order('size_value ' . $this->order)
                ->limit($limit);

            $selects[] = $axisSelect;
        }

        $selects[0]->combine($selects[1]);

        $select
            ->join(
                ['tbl' => $selects[0]],
                'item.id = tbl.item_id',
                []
            )
            ->group('item.id');

        if ($this->order === 'asc') {
            $select->order('min(tbl.size_value) ' . $this->order);
        } else {
            $select->order('max(tbl.size_value) ' . $this->order);
        }

        $result = [];

        foreach ($this->itemTable->selectWith($select) as $car) {
            $result[] = [
                'car'       => $car,
                'valueHtml' => $this->getBrakesText($car),
            ];
        }

        return [
            'unit' => null,
            'cars' => $result,
        ];
    }

    /**
     * @param array|ArrayAccess $car
     * @throws Exception
     */
    protected function getBrakesText($car): string
    {
        $text = [];

        $rear  = $this->attributes['rear'];
        $front = $this->attributes['front'];

        $specService = $this->most->getSpecs();

        foreach ([$front, $rear] as $axis) {
            $diameterValue  = $specService->getActualValue($axis['diameter'], $car['id']);
            $thicknessValue = $specService->getActualValue($axis['thickness'], $car['id']);

            if ($diameterValue || $thicknessValue) {
                $value = $diameterValue . ' × ' . $thicknessValue . ' <span class="unit">мм</span>';

                if ($value) {
                    $text[$value] = 0;
                }
            }
        }

        return implode("<br />", array_keys($text));
    }
}
