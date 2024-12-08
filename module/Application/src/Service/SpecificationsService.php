<?php

namespace Application\Service;

use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Picture;
use Application\Model\VehicleType;
use Application\Spec\Table\Car as CarSpecTable;
use ArrayAccess;
use ArrayObject;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\Json\Json;
use NumberFormatter;

use function array_diff;
use function array_intersect;
use function array_keys;
use function array_merge;
use function Autowp\Commons\currentFromResultSetInterface;
use function count;
use function implode;
use function is_array;
use function reset;

class SpecificationsService
{
    private const
        DEFAULT_ZONE_ID = 1,
        ENGINE_ZONE_ID  = 5,
        BUS_ZONE_ID     = 3;

    private const BUS_VEHICLE_TYPES = [19, 39, 28, 32];

    private const
        TOP_PERSPECTIVES    = [10, 1, 7, 8, 11, 12, 2, 4, 13, 5],
        BOTTOM_PERSPECTIVES = [13, 2, 9, 6, 5];

    public const NULL_VALUE_STR = '-';

    private TableGateway $attributeTable;

    private TableGateway $listOptionsTable;

    private array $listOptions = [];

    private TableGateway $unitTable;

    private array $units;

    private TableGateway $userValueTable;

    private array $attributes;

    private array $childs;

    private array $zoneAttrs = [];

    private TranslatorInterface $translator;

    private ItemNameFormatter $itemNameFormatter;

    private Item $itemModel;

    private ItemParent $itemParent;

    private Picture $picture;

    private VehicleType $vehicleType;

    private TableGateway $zoneAttributeTable;

    private TableGateway $valueTable;

    private TableGateway $valueFloatTable;

    private TableGateway $valueIntTable;

    private TableGateway $valueListTable;

    private TableGateway $valueStringTable;

    private RabbitMQ $rabbitmq;

    public function __construct(
        TranslatorInterface $translator,
        ItemNameFormatter $itemNameFormatter,
        Item $itemModel,
        ItemParent $itemParent,
        Picture $picture,
        VehicleType $vehicleType,
        TableGateway $unitTable,
        TableGateway $listOptionsTable,
        TableGateway $attributeTable,
        TableGateway $zoneAttributeTable,
        TableGateway $userValueTable,
        TableGateway $valueTable,
        TableGateway $valueFloatTable,
        TableGateway $valueIntTable,
        TableGateway $valueListTable,
        TableGateway $valueStringTable,
        RabbitMQ $rabbitmq
    ) {
        $this->translator         = $translator;
        $this->itemNameFormatter  = $itemNameFormatter;
        $this->itemModel          = $itemModel;
        $this->itemParent         = $itemParent;
        $this->picture            = $picture;
        $this->vehicleType        = $vehicleType;
        $this->unitTable          = $unitTable;
        $this->listOptionsTable   = $listOptionsTable;
        $this->attributeTable     = $attributeTable;
        $this->zoneAttributeTable = $zoneAttributeTable;
        $this->userValueTable     = $userValueTable;
        $this->valueTable         = $valueTable;
        $this->valueFloatTable    = $valueFloatTable;
        $this->valueIntTable      = $valueIntTable;
        $this->valueListTable     = $valueListTable;
        $this->valueStringTable   = $valueStringTable;
        $this->rabbitmq           = $rabbitmq;
    }

    private function loadUnits(): void
    {
        if (! isset($this->units)) {
            $units = [];
            foreach ($this->unitTable->select([]) as $unit) {
                $id         = (int) $unit['id'];
                $units[$id] = [
                    'id'   => $id,
                    'name' => $unit['name'],
                    'abbr' => $unit['abbr'],
                ];
            }

            $this->units = $units;
        }
    }

    public function getUnit(int $id): ?array
    {
        $this->loadUnits();

        return $this->units[$id] ?? null;
    }

    public function getZoneIdByCarTypeId(int $itemTypeId, array $vehicleTypeIds): int
    {
        if ($itemTypeId === Item::ENGINE) {
            return self::ENGINE_ZONE_ID;
        }

        $zoneId = self::DEFAULT_ZONE_ID;

        if (array_intersect($vehicleTypeIds, self::BUS_VEHICLE_TYPES)) {
            $zoneId = self::BUS_ZONE_ID;
        }

        return $zoneId;
    }

    private function loadListOptions(array $attributeIds): void
    {
        $ids = array_diff($attributeIds, array_keys($this->listOptions));

        if (count($ids)) {
            $select = new Sql\Select($this->listOptionsTable->getTable());
            $select
                ->where(new Sql\Predicate\In('attribute_id', $ids))
                ->order('position');
            $rows = $this->listOptionsTable->selectWith($select);

            foreach ($rows as $row) {
                $aid = (int) $row['attribute_id'];
                $id  = (int) $row['id'];
                if (! isset($this->listOptions[$aid])) {
                    $this->listOptions[$aid] = [];
                }
                $this->listOptions[$aid][$id] = $row['name'];
            }
        }
    }

    /**
     * @throws Exception
     */
    private function getListOptionsText(int $attributeId, int $id): string
    {
        $this->loadListOptions([$attributeId]);

        if (! isset($this->listOptions[$attributeId][$id])) {
            throw new Exception("list option `$id` not found");
        }

        return $this->translator->translate($this->listOptions[$attributeId][$id], 'default');
    }

    private function loadZone(int $id): array
    {
        if (isset($this->zoneAttrs[$id])) {
            return $this->zoneAttrs[$id];
        }

        $select = new Sql\Select($this->zoneAttributeTable->getTable());
        $select->columns(['attribute_id'])
            ->where(['zone_id' => $id])
            ->order('position');

        $result = [];
        foreach ($this->zoneAttributeTable->selectWith($select) as $row) {
            $result[] = (int) $row['attribute_id'];
        }

        $this->zoneAttrs[$id] = $result;

        return $result;
    }

    private function loadAttributes(): self
    {
        if (! isset($this->attributes)) {
            $array  = [];
            $childs = [];

            $select = new Sql\Select($this->attributeTable->getTable());
            $select->order('position');

            foreach ($this->attributeTable->selectWith($select) as $row) {
                $id         = (int) $row['id'];
                $pid        = (int) $row['parent_id'];
                $array[$id] = [
                    'id'          => $id,
                    'name'        => $row['name'],
                    'description' => $row['description'],
                    'typeId'      => (int) $row['type_id'],
                    'unitId'      => (int) $row['unit_id'],
                    'isMultiple'  => $row['multiple'],
                    'precision'   => $row['precision'],
                    'parentId'    => $pid ? $pid : null,
                ];
                if (! isset($childs[$id])) {
                    $childs[$id] = [];
                }
                if (! isset($childs[$pid])) {
                    $childs[$pid] = [$id];
                } else {
                    $childs[$pid][] = $id;
                }
            }

            $this->attributes = $array;
            $this->childs     = $childs;
        }

        return $this;
    }

    public function getAttribute(int $id): ?array
    {
        $this->loadAttributes();

        return $this->attributes[$id] ?? null;
    }

    /**
     * @param array|ArrayAccess $car
     * @return array|ArrayObject|null
     * @throws Exception
     */
    private function specPicture($car, ?array $perspectives)
    {
        $order = [];
        if ($perspectives) {
            foreach ($perspectives as $pid) {
                $order[] = new Sql\Expression('picture_item.perspective_id = ? DESC', [$pid]);
            }
        } else {
            $order[] = 'pictures.id desc';
        }

        return $this->picture->getRow([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'ancestor_or_self' => $car['id'],
            ],
            'order'  => $order,
            'group'  => ['picture_item.perspective_id'],
        ]);
    }

    public function getAttributes(array $options = []): array
    {
        $defaults = [
            'zone'      => null,
            'parent'    => null,
            'recursive' => false,
        ];
        $options  = array_merge($defaults, $options);

        $zone      = $options['zone'];
        $parent    = $options['parent'];
        $recursive = $options['recursive'];

        $this->loadAttributes();

        if ($zone) {
            $this->loadZone($zone);
        }

        $attributes = [];
        if ($recursive) {
            $ids = [];
            if ($zone) {
                if (isset($this->childs[$parent])) {
                    $ids = array_intersect($this->zoneAttrs[$zone], $this->childs[$parent]);
                }
            } else {
                if (isset($this->childs[$parent])) {
                    $ids = $this->childs[$parent];
                }
            }
            foreach ($ids as $id) {
                $attributes[] = $this->attributes[$id];
            }
        } else {
            if ($zone) {
                $attributes = [];
                if ($parent !== null) {
                    $ids = [];
                    if (isset($this->childs[$parent])) {
                        $ids = array_intersect($this->zoneAttrs[$zone], $this->childs[$parent]);
                    }
                } else {
                    $ids = $this->zoneAttrs[$zone];
                }
                foreach ($ids as $id) {
                    $attributes[] = $this->attributes[$id];
                }
            } else {
                if ($parent !== null) {
                    foreach ($this->childs[$parent] as $id) {
                        $attributes[] = $this->attributes[$id];
                    }
                } else {
                    $attributes = $this->attributes;
                }
            }
        }

        if ($recursive) {
            foreach ($attributes as &$attr) {
                $attr['childs'] = $this->getAttributes([
                    'zone'      => $zone,
                    'parent'    => $attr['id'],
                    'recursive' => $recursive,
                ]);
            }
        }

        return $attributes;
    }

    /**
     * @throws Exception
     * @return null|mixed
     */
    public function getActualValue(int $attributeId, int $itemId)
    {
        if (! $itemId) {
            throw new Exception("item_id not set");
        }

        $attribute = $this->getAttribute($attributeId);
        if (! $attribute) {
            throw new Exception("attribute `$attributeId` not found");
        }

        $valuesTable = $this->getValueDataTable($attribute['typeId']);

        $select = new Sql\Select($valuesTable->getTable());
        $select->columns(['value'])
            ->where([
                'attribute_id' => $attribute['id'],
                'item_id'      => $itemId,
            ]);

        if ($attribute['isMultiple']) {
            $select->order('ordering');

            $rows = $valuesTable->selectWith($select);

            $values = [];
            foreach ($rows as $row) {
                $values[] = $row['value'];
            }

            if (count($values)) {
                return $values;
            }
        } else {
            $row = currentFromResultSetInterface($valuesTable->selectWith($select));

            if ($row) {
                return $row['value'];
            }
        }

        return null;
    }

    /**
     * @throws Exception
     */
    public function specifications(array $cars, array $options): CarSpecTable
    {
        $options = array_merge([
            'contextCarId' => null,
            'language'     => 'en',
        ], $options);

        $language     = $options['language'];
        $contextCarId = (int) $options['contextCarId'];

        $result = [];

        $zoneIds = [];
        foreach ($cars as $car) {
            $vehicleTypeIds = $this->vehicleType->getVehicleTypes($car['id']);
            $zoneId         = $this->getZoneIdByCarTypeId($car['item_type_id'], $vehicleTypeIds);

            $zoneIds[$zoneId] = true;
        }

        $zoneMixed = count($zoneIds) > 1;

        if ($zoneMixed) {
            $specsZoneId = null;
        } else {
            $keys        = array_keys($zoneIds);
            $specsZoneId = reset($keys);
        }

        $attributes = $this->getAttributes([
            'zone'      => $specsZoneId,
            'recursive' => true,
            'parent'    => 0,
        ]);

        $engineNameAttr = 100;

        $carIds = [];
        foreach ($cars as $car) {
            $carIds[] = $car['id'];
        }

        if ($specsZoneId) {
            $this->loadListOptions($this->zoneAttrs[$specsZoneId]);
            $actualValues = $this->getZoneItemsActualValues($specsZoneId, $carIds);
        } else {
            $actualValues = $this->getItemsActualValues($carIds);
        }

        foreach ($actualValues as &$itemActualValues) {
            foreach ($itemActualValues as $attributeId => &$value) {
                $attribute = $this->getAttribute($attributeId);
                if (! $attribute) {
                    throw new Exception("Attribute `$attributeId` not found");
                }
                $value = $this->valueToText($attribute, $value, $language);
            }
            unset($value); // prevent future bugs
        }
        unset($itemActualValues); // prevent future bugs

        foreach ($cars as $car) {
            $itemId = (int) $car['id'];

            //$values = $this->loadValues($attributes, $itemId);
            $values = $actualValues[$itemId] ?? [];

            // append engine name
            if (! (isset($values[$engineNameAttr]) && $values[$engineNameAttr]) && $car['engine_item_id']) {
                $engineRow = currentFromResultSetInterface(
                    $this->itemModel->getTable()->select(['id' => (int) $car['engine_item_id']])
                );
                if ($engineRow) {
                    $values[$engineNameAttr] = $engineRow['name'];
                }
            }

            $name = null;
            if ($contextCarId) {
                $name = $this->itemParent->getNamePreferLanguage($contextCarId, $car['id'], $language);
            }
            if (! $name) {
                $name = $this->itemNameFormatter->format($this->itemModel->getNameData($car, $language), $language);
            }

            $topPicture        = $this->specPicture($car, self::TOP_PERSPECTIVES);
            $topPictureRequest = null;
            if ($topPicture) {
                $topPictureRequest = $topPicture['image_id'];
            }
            $bottomPicture        = $this->specPicture($car, self::BOTTOM_PERSPECTIVES);
            $bottomPictureRequest = null;
            if ($bottomPicture) {
                $bottomPictureRequest = $bottomPicture['image_id'];
            }

            $result[] = [
                'id'                   => $itemId,
                'name'                 => $name,
                'beginYear'            => $car['begin_year'],
                'endYear'              => $car['end_year'],
                'produced'             => $car['produced'],
                'produced_exactly'     => $car['produced_exactly'],
                'topPicture'           => $topPicture,
                'topPictureRequest'    => $topPictureRequest,
                'bottomPicture'        => $bottomPicture,
                'bottomPictureRequest' => $bottomPictureRequest,
                'carType'              => null,
                'values'               => $values,
            ];
        }

        // remove empty attributes
        $this->removeEmpty($attributes, $result);

        // load units
        $this->addUnitsToAttributes($attributes);

        return new CarSpecTable($result, $attributes);
    }

    private function addUnitsToAttributes(array &$attributes): void
    {
        foreach ($attributes as &$attribute) {
            if ($attribute['unitId']) {
                $attribute['unit'] = $this->getUnit($attribute['unitId']);
            }

            $this->addUnitsToAttributes($attribute['childs']);
        }
    }

    private function removeEmpty(array &$attributes, array $cars): void
    {
        foreach ($attributes as $idx => &$attribute) {
            $this->removeEmpty($attribute['childs'], $cars);

            if (count($attribute['childs']) > 0) {
                $haveValue = true;
            } else {
                $id        = $attribute['id'];
                $haveValue = false;
                foreach ($cars as $car) {
                    if (isset($car['values'][$id])) {
                        $haveValue = true;
                        break;
                    }
                }
            }

            if (! $haveValue) {
                unset($attributes[$idx]);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function getValueDataTable(int $type): TableGateway
    {
        switch ($type) {
            case 1: // string
                return $this->valueStringTable;

            case 2: // int
            case 5: // checkbox
                return $this->valueIntTable;

            case 3: // float
                return $this->valueFloatTable;

            case 6: // select
            case 7: // select
                return $this->valueListTable;
        }
        throw new Exception("Unexpected type `$type`");
    }

    /**
     * @param mixed $value
     * @return mixed|null
     * @throws Exception
     */
    private function valueToText(array $attribute, $value, string $language)
    {
        if ($value === null) {
            return null;
        }

        switch ($attribute['typeId']) {
            case 1: // string
                return $value;

            case 2: // int
                $formatter = new NumberFormatter($language, NumberFormatter::DECIMAL);
                return $formatter->format($value);

            case 3: // float
                $formatter = new NumberFormatter($language, NumberFormatter::DECIMAL);
                if ($attribute['precision']) {
                    $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $attribute['precision']);
                }

                return $formatter->format($value, NumberFormatter::TYPE_DOUBLE);

            case 4: // textarea
                return $value;

            case 5: // checkbox
                return $value ? 'да' : 'нет';

            case 6: // select
            case 7: // select
                if ($value) {
                    if (is_array($value)) {
                        $text     = [];
                        $nullText = false;
                        foreach ($value as $v) {
                            if ($v === null) {
                                $text[]   = null;
                                $nullText = true;
                            } else {
                                $text[] = $this->getListOptionsText($attribute['id'], $v);
                            }
                        }
                        return $nullText ? null : implode(', ', $text);
                    } else {
                        return $this->getListOptionsText($attribute['id'], $value);
                    }
                }
                break;
        }
        return null;
    }

    /**
     * @param int|array $itemId
     * @return bool|array
     * @throws Exception
     */
    public function hasSpecs($itemId)
    {
        $select = new Sql\Select($this->valueTable->getTable());
        $select->columns(['item_id']);
        if (is_array($itemId)) {
            if (count($itemId) <= 0) {
                return false;
            }

            $select->quantifier($select::QUANTIFIER_DISTINCT)
                ->where([new Sql\Predicate\In('item_id', $itemId)]);

            $result = [];
            foreach ($itemId as $id) {
                $result[(int) $id] = false;
            }

            foreach ($this->valueTable->selectWith($select) as $row) {
                $result[(int) $row['item_id']] = true;
            }

            return $result;
        }

        $select->where(['item_id' => (int) $itemId])
            ->limit(1);

        return (bool) currentFromResultSetInterface($this->valueTable->selectWith($select));
    }

    /**
     * @throws Exception
     */
    public function getSpecsCount(int $itemId): int
    {
        $select = new Sql\Select($this->valueTable->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where(['item_id' => $itemId]);

        $row = currentFromResultSetInterface($this->valueTable->selectWith($select));

        return $row ? (int) $row['count'] : 0;
    }

    /**
     * @param int|array $itemId
     * @return bool|array
     * @throws Exception
     */
    public function hasChildSpecs($itemId)
    {
        $select = new Sql\Select($this->valueTable->getTable());

        $select->columns([])
            ->join('item_parent', 'attrs_values.item_id = item_parent.item_id', ['parent_id']);
        if (is_array($itemId)) {
            if (count($itemId) <= 0) {
                return [];
            }

            $select->quantifier($select::QUANTIFIER_DISTINCT)
                ->where([new Sql\Predicate\In('item_parent.parent_id', $itemId)]);

            $result = [];
            foreach ($itemId as $id) {
                $result[(int) $id] = false;
            }
            foreach ($this->valueTable->selectWith($select) as $row) {
                $result[(int) $row['parent_id']] = true;
            }
            return $result;
        }

        $select->where(['item_parent.parent_id' => $itemId]);
        return (bool) currentFromResultSetInterface($this->valueTable->selectWith($select));
    }

    /**
     * @throws Exception
     */
    public function updateInheritedValues(int $itemId): void
    {
        $this->rabbitmq->send('attrs_update_values', Json::encode([
            'type'    => 'inherited',
            'item_id' => $itemId,
        ]));
    }

    public function getContributors(int $itemId): array
    {
        if (! $itemId) {
            return [];
        }

        $select = new Sql\Select($this->userValueTable->getTable());
        $select->columns(['user_id', 'c' => new Sql\Expression('COUNT(1)')])
            ->where([new Sql\Predicate\In('item_id', (array) $itemId)])
            ->group('user_id')
            ->order('c desc');

        $result = [];
        foreach ($this->userValueTable->selectWith($select) as $row) {
            $result[(int) $row['user_id']] = (int) $row['c'];
        }

        return $result;
    }

    /**
     * @param mixed $value
     * @return mixed|null
     */
    private function prepareValue(int $typeId, $value)
    {
        switch ($typeId) {
            case 1: // string
                return $value;

            case 2: // int
                return $value;

            case 3: // float
                return $value;

            case 4: // textarea
                return $value;

            case 5: // checkbox
                return $value === null ? null : ($value ? 1 : 0);

            case 6: // select
            case 7: // tree select
                return $value === null ? null : (int) $value;
        }
        return null;
    }

    /**
     * @throws Exception
     */
    public function getActualValueText(int $attributeId, int $itemId, string $language): ?string
    {
        if (! $itemId) {
            throw new Exception("item_id not set");
        }

        $attribute = $this->getAttribute($attributeId);
        if (! $attribute) {
            throw new Exception("attribute not found");
        }

        $value = $this->getActualValue($attribute['id'], $itemId);

        if ($attribute['isMultiple'] && is_array($value)) {
            $text = [];
            foreach ($value as $v) {
                $text[] = $this->valueToText($attribute, $v, $language);
            }
            return implode(', ', $text);
        } else {
            return $this->valueToText($attribute, $value, $language);
        }
    }

    /**
     * @throws Exception
     */
    private function getItemsActualValues(array $itemIds): array
    {
        if (count($itemIds) <= 0) {
            return [];
        }

        $requests = [
            1 => false,
            2 => false, /* , 5*/
            3 => false,
            //4 => [false],
            6 => true, /* , 7 */
        ];

        $values = [];
        foreach ($requests as $typeId => $isMultiple) {
            $valueDataTable = $this->getValueDataTable($typeId);

            $select = new Sql\Select($valueDataTable->getTable());
            $select->where([new Sql\Predicate\In('item_id', $itemIds)]);

            if ($isMultiple) {
                $select->order('ordering');
            }

            foreach ($valueDataTable->selectWith($select) as $row) {
                $aid   = (int) $row['attribute_id'];
                $id    = (int) $row['item_id'];
                $value = $this->prepareValue($typeId, $row['value']);
                if (! isset($values[$id])) {
                    $values[$id] = [];
                }

                $attribute = $this->getAttribute($aid);
                if (! $attribute) {
                    throw new Exception("attribute `$aid` not found");
                }

                if ($attribute['isMultiple']) {
                    if (! isset($values[$id][$aid])) {
                        $values[$id][$aid] = [];
                    }
                    $values[$id][$aid][] = $value;
                } else {
                    $values[$id][$aid] = $value;
                }
            }
        }

        return $values;
    }

    /**
     * @throws Exception
     */
    private function getZoneItemsActualValues(int $zoneId, array $itemIds): array
    {
        if (count($itemIds) <= 0) {
            return [];
        }

        $this->loadZone($zoneId);

        $attributes = $this->getAttributes([
            'zone'   => $zoneId,
            'parent' => null,
        ]);

        $requests = [];

        foreach ($attributes as $attribute) {
            $typeId     = $attribute['typeId'];
            $isMultiple = $attribute['isMultiple'] ? 1 : 0;
            if ($typeId) {
                if (! isset($requests[$typeId][$isMultiple])) {
                    $requests[$typeId][$isMultiple] = [];
                }
                $requests[$typeId][$isMultiple][] = $attribute['id'];
            }
        }

        $values = [];
        foreach ($requests as $typeId => $multiples) {
            $valueDataTable = $this->getValueDataTable($typeId);

            foreach ($multiples as $isMultiple => $ids) {
                $select = new Sql\Select($valueDataTable->getTable());
                $select->where([
                    new Sql\Predicate\In('attribute_id', $ids),
                    new Sql\Predicate\In('item_id', $itemIds),
                ]);

                if ($isMultiple) {
                    $select->order('ordering');
                }

                foreach ($valueDataTable->selectWith($select) as $row) {
                    $aid   = (int) $row['attribute_id'];
                    $id    = (int) $row['item_id'];
                    $value = $this->prepareValue($typeId, $row['value']);
                    if (! isset($values[$id])) {
                        $values[$id] = [];
                    }
                    if ($isMultiple) {
                        if (! isset($values[$id][$aid])) {
                            $values[$id][$aid] = [];
                        }
                        $values[$id][$aid][] = $value;
                    } else {
                        $values[$id][$aid] = $value;
                    }
                }
            }
        }

        return $values;
    }
}
