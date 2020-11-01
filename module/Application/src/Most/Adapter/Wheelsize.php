<?php

namespace Application\Most\Adapter;

use Application\WheelSize as WheelsizeObject;
use ArrayAccess;
use Exception;
use Laminas\Db\Sql;

use function array_keys;
use function Autowp\Commons\currentFromResultSetInterface;
use function implode;

class Wheelsize extends AbstractAdapter
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
        $wheel = $this->attributes['rear'];

        $specService = $this->most->getSpecs();

        $tyrewidth            = currentFromResultSetInterface(
            $this->attributeTable->select(['id' => $wheel['tyrewidth']])
        );
        $tyrewidthValuesTable = $specService->getValueDataTable($tyrewidth['type_id'])->getTable();

        $tyreseries            = currentFromResultSetInterface(
            $this->attributeTable->select(['id' => $wheel['tyreseries']])
        );
        $tyreseriesValuesTable = $specService->getValueDataTable($tyreseries['type_id'])->getTable();

        $radius            = currentFromResultSetInterface(
            $this->attributeTable->select(['id' => $wheel['radius']])
        );
        $radiusValuesTable = $specService->getValueDataTable($radius['type_id'])->getTable();

        $select
            ->join(['tyrewidth' => $tyrewidthValuesTable], 'item.id = tyrewidth.item_id', [])
            ->join(['tyreseries' => $tyreseriesValuesTable], 'item.id = tyreseries.item_id', [])
            ->join(['radius' => $radiusValuesTable], 'item.id = radius.item_id', [])
            ->where([
                'tyrewidth.attribute_id' => $tyrewidth['id'],
                'tyrewidth.value > 0',
                'tyreseries.attribute_id' => $tyreseries['id'],
                'tyreseries.value > 0',
                'radius.attribute_id' => $radius['id'],
                'radius.value > 0',
            ])
            ->group(['item.id', 'tyrewidth.value', 'tyreseries.value', 'radius.value'])
            ->order(new Sql\Expression('2*tyrewidth.value*tyreseries.value/100+radius.value*25.4 ' . $this->order));

        $cars = $this->itemTable->selectWith($select);

        $result = [];

        foreach ($cars as $car) {
            $result[] = [
                'car'       => $car,
                'valueText' => $this->getWheelSizeText($car),
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
    protected function getWheelSizeText($car): string
    {
        $text = [];

        $specService = $this->most->getSpecs();

        foreach ($this->attributes as $wheel) {
            $wheelObj = new WheelsizeObject(
                $specService->getActualValue($wheel['tyrewidth'], $car['id']),
                $specService->getActualValue($wheel['tyreseries'], $car['id']),
                $specService->getActualValue($wheel['radius'], $car['id']),
                null
            );
            $value    = $wheelObj->getTyreName();
            if ($value) {
                $text[$value] = 0;
            }
        }

        return implode(', ', array_keys($text));
    }
}
