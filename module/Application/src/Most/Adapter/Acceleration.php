<?php

namespace Application\Most\Adapter;

use ArrayAccess;
use ArrayObject;
use Exception;
use Laminas\Db\Sql;

use function Autowp\Commons\currentFromResultSetInterface;
use function implode;

class Acceleration extends AbstractAdapter
{
    protected array $attributes;

    protected string $order;

    private const MPH60_TO_KMH100 = 0.98964381346271110050637609692728;

    /** @var array|ArrayObject|null */
    private $kmhAttribute;

    /** @var array|ArrayObject|null */
    private $mphAttribute;

    /**
     * @throws Exception
     */
    public function setAttributes(array $value): void
    {
        $this->attributes = $value;

        $this->kmhAttribute = currentFromResultSetInterface(
            $this->attributeTable->select(['id' => $this->attributes['to100kmh']])
        );
        $this->mphAttribute = currentFromResultSetInterface(
            $this->attributeTable->select(['id' => $this->attributes['to60mph']])
        );
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
        $axises = [
            [
                'attr' => $this->kmhAttribute,
                'q'    => 1,
            ],
            [
                'attr' => $this->mphAttribute,
                'q'    => self::MPH60_TO_KMH100,
            ],
        ];

        $wheres = $select->getRawState($select::WHERE);
        $joins  = $select->getRawState($select::JOINS)->getJoins();
        unset($joins['cars']);

        $limit = $this->most->getCarsCount();

        $axisBaseSelect = new Sql\Select('item');
        $axisBaseSelect->columns([]);
        if ($wheres) {
            $axisBaseSelect->where($wheres);
        }
        foreach ($joins as $join) {
            if ($join['type'] === Sql\Join::JOIN_INNER) {
                $axisBaseSelect->join($join['name'], $join['on'], $join['columns']);
            }
        }
        $axisBaseSelect->reset($select::COLUMNS);

        $specService = $this->most->getSpecs();

        $selects = [];
        foreach ($axises as $axis) {
            $axisSelect = clone $axisBaseSelect;

            $attr = $axis['attr'];

            $attrValuesTable = $specService->getValueDataTable($attr['type_id'])->getTable();

            $valueColumn = $axis['q'] !== 1 ? new Sql\Expression('axis.value / ?', [$axis['q']]) : 'axis.value';

            $axisSelect
                ->columns(['item_id' => 'item.id', 'size_value' => $valueColumn])
                ->join(['axis' => $attrValuesTable], 'item.id = axis.item_id', [])
                ->where([
                    'axis.attribute_id' => $attr['id'],
                    'axis.value > 0',
                ])
                ->order('size_value ' . $this->order)
                ->limit($limit);

            $selects[] = $axisSelect->getSqlString($this->itemTable->getAdapter()->getPlatform());
        }

        $select
            ->join(
                ['tbl' => new Sql\Expression('((' . $selects[0] . ') UNION (' . $selects[1] . '))')],
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
                'valueHtml' => $this->getText($car, $language),
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
    protected function getText($car, string $language): string
    {
        $text = [];

        $axises = [
            [
                'attr' => $this->kmhAttribute,
                'unit' => 'сек до&#xa0;100&#xa0;км/ч',
            ],
            [
                'attr' => $this->mphAttribute,
                'unit' => 'сек до&#xa0;60&#xa0;миль/ч',
            ],
        ];

        $specService = $this->most->getSpecs();

        foreach ($axises as $axis) {
            $value = $specService->getActualValueText($axis['attr']['id'], $car['id'], $language);

            if ($value > 0) {
                $text[] = $value . ' <span class="unit">' . $axis['unit'] . '</span>';
            }
        }

        return implode("<br />", $text);
    }
}
