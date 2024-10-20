<?php

namespace Application\Service;

use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Picture;
use Application\Model\VehicleType;
use Application\Spec\Table\Car as CarSpecTable;
use Application\Validator\Attrs\IsFloatOrNull;
use Application\Validator\Attrs\IsIntOrNull;
use ArrayAccess;
use ArrayObject;
use Autowp\User\Model\User;
use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\InputFilter\ArrayInput;
use Laminas\InputFilter\Input;
use NumberFormatter;

use function array_diff;
use function array_intersect;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_replace;
use function array_unique;
use function Autowp\Commons\currentFromResultSetInterface;
use function count;
use function implode;
use function in_array;
use function is_array;
use function reset;
use function serialize;
use function strlen;

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

    private const
        WEIGHT_NONE          = 0,
        WEIGHT_FIRST_ACTUAL  = 1,
        WEIGHT_SECOND_ACTUAL = 0.1,
        WEIGHT_WRONG         = -1;

    private TableGateway $attributeTable;

    private TableGateway $listOptionsTable;

    private array $listOptions = [];

    private array $listOptionsChilds = [];

    private TableGateway $unitTable;

    private array $units;

    private TableGateway $userValueTable;

    private array $attributes;

    private array $childs;

    private array $zoneAttrs = [];

    private array $carChildsCache = [];

    private array $engineAttributes;

    private TableGateway $typeTable;

    private array $types;

    private User $userModel;

    private array $valueWeights = [];

    private TranslatorInterface $translator;

    private ItemNameFormatter $itemNameFormatter;

    private Item $itemModel;

    private ItemParent $itemParent;

    private Picture $picture;

    private VehicleType $vehicleType;

    private TableGateway $zoneAttributeTable;

    private TableGateway $userValueFloatTable;

    private TableGateway $userValueIntTable;

    private TableGateway $userValueListTable;

    private TableGateway $userValueStringTable;

    private TableGateway $valueTable;

    private TableGateway $valueFloatTable;

    private TableGateway $valueIntTable;

    private TableGateway $valueListTable;

    private TableGateway $valueStringTable;

    public function __construct(
        TranslatorInterface $translator,
        ItemNameFormatter $itemNameFormatter,
        Item $itemModel,
        ItemParent $itemParent,
        Picture $picture,
        VehicleType $vehicleType,
        User $userModel,
        TableGateway $unitTable,
        TableGateway $listOptionsTable,
        TableGateway $typeTable,
        TableGateway $attributeTable,
        TableGateway $zoneAttributeTable,
        TableGateway $userValueTable,
        TableGateway $userValueFloatTable,
        TableGateway $userValueIntTable,
        TableGateway $userValueListTable,
        TableGateway $userValueStringTable,
        TableGateway $valueTable,
        TableGateway $valueFloatTable,
        TableGateway $valueIntTable,
        TableGateway $valueListTable,
        TableGateway $valueStringTable
    ) {
        $this->translator        = $translator;
        $this->itemNameFormatter = $itemNameFormatter;
        $this->itemModel         = $itemModel;
        $this->itemParent        = $itemParent;
        $this->picture           = $picture;
        $this->vehicleType       = $vehicleType;
        $this->userModel         = $userModel;

        $this->unitTable            = $unitTable;
        $this->listOptionsTable     = $listOptionsTable;
        $this->typeTable            = $typeTable;
        $this->attributeTable       = $attributeTable;
        $this->zoneAttributeTable   = $zoneAttributeTable;
        $this->userValueTable       = $userValueTable;
        $this->userValueFloatTable  = $userValueFloatTable;
        $this->userValueIntTable    = $userValueIntTable;
        $this->userValueListTable   = $userValueListTable;
        $this->userValueStringTable = $userValueStringTable;
        $this->valueTable           = $valueTable;
        $this->valueFloatTable      = $valueFloatTable;
        $this->valueIntTable        = $valueIntTable;
        $this->valueListTable       = $valueListTable;
        $this->valueStringTable     = $valueStringTable;
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
                $pid = (int) $row['parent_id'];
                if (! isset($this->listOptions[$aid])) {
                    $this->listOptions[$aid] = [];
                }
                $this->listOptions[$aid][$id] = $row['name'];
                if (! isset($this->listOptionsChilds[$aid][$pid])) {
                    $this->listOptionsChilds[$aid][$pid] = [$id];
                } else {
                    $this->listOptionsChilds[$aid][$pid][] = $id;
                }
            }
        }
    }

    private function getListsOptions(array $attributeIds): array
    {
        $this->loadListOptions($attributeIds);

        $result = [];
        foreach ($attributeIds as $aid) {
            if (isset($this->listOptions[$aid])) {
                $result[$aid] = $this->getListOptions($aid, 0);
            }
        }

        return $result;
    }

    private function getListOptions(int $aid, int $parentId): array
    {
        $result = [];
        if (isset($this->listOptionsChilds[$aid][$parentId])) {
            foreach ($this->listOptionsChilds[$aid][$parentId] as $childId) {
                $result[(int) $childId] = $this->translator->translate($this->listOptions[$aid][$childId]);
                $childOptions           = $this->getListOptions($aid, $childId);
                foreach ($childOptions as &$value) {
                    $value = '…' . $this->translator->translate($value);
                }
                unset($value); // prevent future bugs
                $result = array_replace($result, $childOptions);
            }
        }
        return $result;
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

    /**
     * @throws Exception
     */
    public function getFilterSpec(int $attributeId): ?array
    {
        $filters    = [];
        $validators = [];

        $attribute = $this->getAttribute($attributeId);
        if (! $attribute) {
            return null;
        }

        $type = null;
        if ($attribute['typeId']) {
            $type = $this->getType($attribute['typeId']);
        }

        if (! $type) {
            return null;
        }

        $multioptions = $this->getListsOptions([$attributeId]);

        $maxlength = null;
        if ($type['maxlength']) {
            $maxlength = $type['maxlength'];
        }

        $inputType = Input::class;

        switch ($type['id']) {
            case 1: // string
                $filters = [['name' => 'StringTrim']];
                if ($maxlength) {
                    $validators[] = [
                        'name'    => 'StringLength',
                        'options' => [
                            'max' => $type['maxlength'],
                        ],
                    ];
                }
                break;

            case 2: // int
                $filters    = [['name' => 'StringTrim']];
                $validators = [
                    [
                        'name'    => IsIntOrNull::class,
                        'options' => ['locale' => 'en_US'],
                    ],
                ];
                break;

            case 3: // float
                $filters    = [['name' => 'StringTrim']];
                $validators = [
                    [
                        'name'    => IsFloatOrNull::class,
                        'options' => ['locale' => 'en_US'],
                    ],
                ];
                break;

            case 4: // textarea
                $filters = [['name' => 'StringTrim']];
                break;

            case 5: // checkbox
                $validators = [
                    [
                        'name'    => 'InArray',
                        'options' => [
                            'haystack' => [
                                '',
                                '-',
                                '0',
                                '1',
                            ],
                        ],
                    ],
                ];
                break;

            case 6: // select
            case 7: // treeselect
                $haystack = [
                    '',
                    '-',
                ];
                if (isset($multioptions[$attribute['id']])) {
                    $haystack = array_merge(
                        $haystack,
                        array_keys($multioptions[$attribute['id']])
                    );
                }
                $validators = [
                    [
                        'name'    => 'InArray',
                        'options' => [
                            'haystack' => $haystack,
                        ],
                    ],
                ];
                $inputType  = ArrayInput::class;
                break;
        }

        return [
            'type'       => $inputType,
            'required'   => false,
            'filters'    => $filters,
            'validators' => $validators,
        ];
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
     * @param mixed $value
     * @throws Exception
     */
    public function setUserValue2(int $uid, int $attributeId, int $itemId, $value, bool $empty): void
    {
        $attribute = $this->getAttribute($attributeId);
        if (! $attribute) {
            throw new Exception("attribute `$attributeId` not found");
        }

        $somethingChanged = false;

        $userValueDataTable = $this->getUserValueDataTable($attribute['typeId']);

        $userValuePrimaryKey = [
            'attribute_id' => $attribute['id'],
            'item_id'      => $itemId,
            'user_id'      => $uid,
        ];

        if ($attribute['isMultiple']) {
            // remove value descriptors
            $this->userValueTable->delete([
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $itemId,
                'user_id = ?'      => $uid,
            ]);

            // remove values
            $userValueDataTable->delete($userValuePrimaryKey);

            if (is_array($value)) {
                foreach ($value as $v) {
                    if ($v === null) {
                        $value = [];
                        break;
                    }
                }
            }

            if ($empty) {
                $value = [null];
            }

            if (count($value)) {
                // insert new descriptors and values
                /** @var Adapter $adapter */
                $adapter = $this->userValueTable->getAdapter();
                $adapter->query('
                    INSERT INTO attrs_user_values (attribute_id, item_id, user_id, add_date, update_date)
                    VALUES (:attribute_id, :item_id, :user_id, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE update_date = VALUES(update_date)
                ', $userValuePrimaryKey);

                $ordering = 1;

                foreach ($value as $oneValue) {
                    $params = array_replace($userValuePrimaryKey, [
                        'ordering' => $ordering,
                        'value'    => $oneValue,
                    ]);
                    /** @var Adapter $adapter */
                    $adapter = $userValueDataTable->getAdapter();
                    $adapter->query('
                        INSERT INTO `' . $userValueDataTable->getTable() . '`
                            (attribute_id, item_id, user_id, ordering, value)
                        VALUES (:attribute_id, :item_id, :user_id, :ordering, :value)
                        ON DUPLICATE KEY UPDATE ordering = VALUES(ordering), value = VALUES(value)
                    ', $params);
                    $ordering++;
                }
            }

            $somethingChanged = $this->updateAttributeActualValue($attribute, $itemId);
        } else {
            if (is_array($value)) {
                $value = count($value) > 0 ? $value[0] : null;
            }

            if (strlen($value) > 0 || $empty) {
                // insert/update value descriptor
                $userValue = currentFromResultSetInterface($this->userValueTable->select($userValuePrimaryKey));

                // insert update value
                $userValueData = currentFromResultSetInterface($userValueDataTable->select($userValuePrimaryKey));

                if ($empty) {
                    $value = null;
                }

                if ($userValueData) {
                    $valueChanged = $value === null
                        ? $userValueData['value'] !== null
                        : $userValueData['value'] !== $value;
                } else {
                    $valueChanged = true;
                }

                if (! $userValue || $valueChanged) {
                    /** @var Adapter $adapter */
                    $adapter = $this->userValueTable->getAdapter();
                    $adapter->query('
                        INSERT INTO attrs_user_values (attribute_id, item_id, user_id, add_date, update_date)
                        VALUES (:attribute_id, :item_id, :user_id, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE update_date = VALUES(update_date)
                    ', $userValuePrimaryKey);

                    $params = array_replace($userValuePrimaryKey, [
                        'value' => $value,
                    ]);
                    /** @var Adapter $adapter */
                    $adapter = $userValueDataTable->getAdapter();
                    $adapter->query('
                        INSERT INTO `' . $userValueDataTable->getTable() . '` (attribute_id, item_id, user_id, value)
                        VALUES (:attribute_id, :item_id, :user_id, :value)
                        ON DUPLICATE KEY UPDATE value = VALUES(value)
                    ', $params);

                    $somethingChanged = $this->updateAttributeActualValue($attribute, $itemId);
                }
            } else {
                // delete value descriptor
                $affected = $this->userValueTable->delete($userValuePrimaryKey);
                // remove value
                $affected += $userValueDataTable->delete($userValuePrimaryKey);

                if ($affected > 0) {
                    $somethingChanged = $this->updateAttributeActualValue($attribute, $itemId);
                }
            }
        }

        if ($somethingChanged) {
            $this->propagateInheritance($attribute, $itemId);

            $this->propageteEngine($attribute, $itemId);

            $this->refreshConflictFlag($attribute['id'], $itemId);
        }
    }

    private function getEngineAttributeIds(): array
    {
        if (isset($this->engineAttributes)) {
            return $this->engineAttributes;
        }

        $select = new Sql\Select($this->attributeTable->getTable());
        $select->columns(['id'])
            ->join('attrs_zone_attributes', 'attrs_attributes.id = attrs_zone_attributes.attribute_id', [])
            ->where(['attrs_zone_attributes.zone_id' => self::ENGINE_ZONE_ID]);

        $result = [];
        foreach ($this->attributeTable->selectWith($select) as $row) {
            $result[] = (int) $row['id'];
        }

        $this->engineAttributes = $result;

        return $result;
    }

    /**
     * @throws Exception
     */
    private function propageteEngine(array $attribute, int $itemId): void
    {
        if (! $this->isEngineAttributeId($attribute['id'])) {
            return;
        }

        if (! $attribute['typeId']) {
            return;
        }

        $vehicles = $this->itemModel->getTable()->select([
            'engine_item_id' => $itemId,
        ]);

        foreach ($vehicles as $vehicle) {
            $this->updateAttributeActualValue($attribute, $vehicle['id']);
        }
    }

    /**
     * @return array|ArrayAccess
     */
    private function getChildCarIds(int $parentId)
    {
        if (! isset($this->carChildsCache[$parentId])) {
            $this->carChildsCache[$parentId] = $this->itemParent->getChildItemsIds($parentId);
        }

        return $this->carChildsCache[$parentId];
    }

    private function haveOwnAttributeValue(int $attributeId, int $itemId): bool
    {
        return (bool) currentFromResultSetInterface($this->userValueTable->select([
            'attribute_id' => $attributeId,
            'item_id'      => $itemId,
        ]));
    }

    /**
     * @throws Exception
     */
    private function propagateInheritance(array $attribute, int $itemId): void
    {
        $childIds = $this->getChildCarIds($itemId);

        foreach ($childIds as $childId) {
            // update only if row use inheritance
            $haveValue = $this->haveOwnAttributeValue($attribute['id'], $childId);

            if (! $haveValue) {
                $value   = $this->calcInheritedValue($attribute, $childId);
                $changed = $this->setActualValue($attribute, $childId, $value);
                if ($changed) {
                    $this->propagateInheritance($attribute, $childId);
                    $this->propageteEngine($attribute, $childId);
                }
            }
        }
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
    public function deleteUserValue(int $attributeId, int $itemId, int $userId): void
    {
        if (! $itemId) {
            throw new Exception("item_id not set");
        }

        $attribute = $this->getAttribute($attributeId);
        if (! $attribute) {
            throw new Exception("attribute not found");
        }

        $valueDataTable = $this->getUserValueDataTable($attribute['typeId']);

        $valueDataTable->delete([
            'attribute_id' => $attributeId,
            'item_id'      => $itemId,
            'user_id'      => $userId,
        ]);

        $this->userValueTable->delete([
            'attribute_id' => $attributeId,
            'item_id'      => $itemId,
            'user_id'      => $userId,
        ]);

        $this->updateActualValue($attribute['id'], $itemId);
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

        $ids = [];
        foreach ($cars as $car) {
            $ids[] = $car['id'];
        }

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
     * @throws Exception
     */
    public function getUserValueDataTable(int $type): TableGateway
    {
        switch ($type) {
            case 1: // string
                return $this->userValueStringTable;
            case 2: // int
            case 5: // checkbox
                return $this->userValueIntTable;
            case 3: // float
                return $this->userValueFloatTable;
            case 6: // select
            case 7: // select
                return $this->userValueListTable;
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
     * @throws Exception
     */
    private function calcAvgUserValue(array $attribute, int $itemId): array
    {
        $userValuesDataTable = $this->getUserValueDataTable($attribute['typeId']);

        $userValueDataRows = $userValuesDataTable->select([
            'attribute_id' => $attribute['id'],
            'item_id'      => $itemId,
        ]);

        // group by users
        $data = [];
        foreach ($userValueDataRows as $userValueDataRow) {
            $uid = $userValueDataRow['user_id'];
            if (! isset($data[$uid])) {
                $data[$uid] = [];
            }
            $data[$uid][] = $userValueDataRow;
        }

        if (count($data) <= 0) {
            return [
                'value' => null,
                'empty' => true,
            ];
        }

        $idx      = 0;
        $registry = $freshness = $ratios = [];
        foreach ($data as $uid => $valueRows) {
            if ($attribute['isMultiple']) {
                $value = [];
                foreach ($valueRows as $valueRow) {
                    $value[$valueRow['ordering']] = $valueRow['value'];
                }
            } else {
                $value = null;
                foreach ($valueRows as $valueRow) {
                    $value = $valueRow['value'];
                }
            }

            $row = currentFromResultSetInterface($this->userValueTable->select([
                'attribute_id' => $attribute['id'],
                'item_id'      => $itemId,
                'user_id'      => $uid,
            ]));
            if (! $row) {
                throw new Exception('Row(rows) without descriptors');
            }

            // look for same value
            $matchRegIdx = null;
            foreach ($registry as $regIdx => $regVal) {
                if ($regVal === $value) {
                    $matchRegIdx = $regIdx;
                }
            }

            if ($matchRegIdx === null) {
                $registry[$idx] = $value;
                $matchRegIdx    = $idx;
                $idx++;
            }

            if (! isset($ratios[$matchRegIdx])) {
                $ratios[$matchRegIdx]    = 0;
                $freshness[$matchRegIdx] = null;
            }
            $ratios[$matchRegIdx] += $this->getUserValueWeight($uid);
            if ($freshness[$matchRegIdx] < $row['update_date']) {
                $freshness[$matchRegIdx] = $row['update_date'];
            }
            //$idx++;
        }

        // select max
        $maxValueRatio = 0;
        $maxValueIdx   = null;
        foreach ($ratios as $idx => $ratio) {
            if ($maxValueIdx === null || $maxValueRatio <= $ratio) {
                $maxValueIdx   = $idx;
                $maxValueRatio = $ratio;
            }
        }
        $actualValue = $registry[$maxValueIdx];
        $empty       = false;

        return [
            'value' => $actualValue,
            'empty' => $empty,
        ];
    }

    private function isEngineAttributeId(int $attrId): bool
    {
        return in_array($attrId, $this->getEngineAttributeIds());
    }

    /**
     * @param array|ArrayAccess $attribute
     * @throws Exception
     */
    private function calcEngineValue($attribute, int $itemId): array
    {
        if (! $this->isEngineAttributeId($attribute['id'])) {
            return [
                'empty' => true,
                'value' => null,
            ];
        }

        $carRow = currentFromResultSetInterface($this->itemModel->getTable()->select([
            'id' => $itemId,
        ]));

        if (! $carRow) {
            return [
                'empty' => true,
                'value' => null,
            ];
        }

        if (! $carRow['engine_item_id']) {
            return [
                'empty' => true,
                'value' => null,
            ];
        }

        $valueDataTable = $this->getValueDataTable($attribute['typeId']);

        if (! $attribute['isMultiple']) {
            $valueDataRow = currentFromResultSetInterface($valueDataTable->select([
                'attribute_id' => $attribute['id'],
                'item_id'      => $carRow['engine_item_id'],
                'value IS NOT NULL',
            ]));

            if ($valueDataRow) {
                return [
                    'empty' => false,
                    'value' => $valueDataRow['value'],
                ];
            } else {
                return [
                    'empty' => true,
                    'value' => null,
                ];
            }
        } else {
            $valueDataRows = $valueDataTable->select([
                'attribute_id' => $attribute['id'],
                'item_id'      => $carRow['engine_item_id'],
                'value IS NOT NULL',
            ]);

            if (count($valueDataRows)) {
                $value = [];
                foreach ($valueDataRows as $valueDataRow) {
                    $value[] = $valueDataRow['value'];
                }

                return [
                    'empty' => false,
                    'value' => $value,
                ];
            } else {
                return [
                    'empty' => true,
                    'value' => null,
                ];
            }
        }
    }

    /**
     * @param array|ArrayAccess $attribute
     * @throws Exception
     */
    private function calcInheritedValue($attribute, int $itemId): array
    {
        $actualValue = [
            'empty' => true,
            'value' => null,
        ];

        $parentIds = $this->itemParent->getParentIds($itemId);

        $valueDataTable = $this->getValueDataTable($attribute['typeId']);

        if (count($parentIds) > 0) {
            if (! $attribute['isMultiple']) {
                $idx      = 0;
                $registry = [];
                $ratios   = [];

                $valueDataRows = $valueDataTable->select([
                    'attribute_id' => $attribute['id'],
                    new Sql\Predicate\In('item_id', $parentIds),
                ]);

                foreach ($valueDataRows as $valueDataRow) {
                    $value = $valueDataRow['value'];

                    // look for same value
                    $matchRegIdx = null;
                    foreach ($registry as $regIdx => $regVal) {
                        if ($regVal === $value) {
                            $matchRegIdx = $regIdx;
                        }
                    }

                    if ($matchRegIdx === null) {
                        $registry[$idx] = $value;
                        $matchRegIdx    = $idx;
                        $idx++;
                    }

                    if (! isset($ratios[$matchRegIdx])) {
                        $ratios[$matchRegIdx] = 0;
                    }
                    $ratios[$matchRegIdx] += 1;
                }

                // select max
                $maxValueRatio = 0;
                $maxValueIdx   = null;
                foreach ($ratios as $idx => $ratio) {
                    if ($maxValueIdx === null || $maxValueRatio <= $ratio) {
                        $maxValueIdx   = $idx;
                        $maxValueRatio = $ratio;
                    }
                }
                if ($maxValueIdx !== null) {
                    $actualValue = [
                        'empty' => false,
                        'value' => $registry[$maxValueIdx],
                    ];
                }
            }
            // TODO: multiple attr inheritance
        }

        return $actualValue;
    }

    /**
     * @throws Exception
     */
    private function setActualValue(array $attribute, int $itemId, array $actualValue): bool
    {
        $valueDataTable = $this->getValueDataTable($attribute['typeId']);

        $somethingChanges = false;

        if ($actualValue['empty']) {
            // descriptor
            $affected = $this->valueTable->delete([
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $itemId,
            ]);
            // value
            $affected += $valueDataTable->delete([
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $itemId,
            ]);
            if ($affected > 0) {
                $somethingChanges = true;
            }
        } else {
            $primaryKey = [
                'attribute_id' => $attribute['id'],
                'item_id'      => $itemId,
            ];

            // descriptor
            /** @var Adapter $adapter */
            $adapter = $this->valueTable->getAdapter();
            $adapter->query('
                INSERT INTO attrs_values (attribute_id, item_id, update_date)
                VALUES (:attribute_id, :item_id, NOW())
                ON DUPLICATE KEY UPDATE update_date = VALUES(update_date)
            ', $primaryKey);

            // value
            if ($attribute['isMultiple']) {
                $affected = $valueDataTable->delete($primaryKey);
                if ($affected > 0) {
                    $somethingChanges = true;
                }

                /** @var Adapter $adapter */
                $adapter = $valueDataTable->getAdapter();
                $stmt    = $adapter->createStatement('
                    INSERT INTO `' . $valueDataTable->getTable() . '` (attribute_id, item_id, ordering, value)
                    VALUES (:attribute_id, :item_id, :ordering, :value)
                    ON DUPLICATE KEY UPDATE ordering = VALUES(ordering), value = VALUES(value)
                ');

                foreach ($actualValue['value'] as $ordering => $value) {
                    $result = $stmt->execute(array_replace([
                        'ordering' => $ordering,
                        'value'    => $value,
                    ], $primaryKey));

                    if ($result->getAffectedRows() > 0) {
                        $somethingChanges = true;
                    }
                }
            } else {
                $params = [
                    'value'        => $actualValue['value'],
                    'attribute_id' => $attribute['id'],
                    'item_id'      => $itemId,
                ];
                /** @var Adapter $adapter */
                $adapter = $valueDataTable->getAdapter();
                $stmt    = $adapter->createStatement('
                    INSERT INTO `' . $valueDataTable->getTable() . '` (attribute_id, item_id, value)
                    VALUES (:attribute_id, :item_id, :value)
                    ON DUPLICATE KEY UPDATE value = VALUES(value)
                ');
                $result  = $stmt->execute($params);

                if ($result->getAffectedRows() > 0) {
                    $somethingChanges = true;
                }
            }

            if ($somethingChanges) {
                $this->valueTable->update([
                    'update_date' => new Sql\Expression('now()'),
                ], $primaryKey);
            }
        }

        return $somethingChanges;
    }

    /**
     * @throws Exception
     */
    public function updateActualValue(int $attributeId, int $itemId): bool
    {
        $attribute = $this->getAttribute($attributeId);
        if (! $attribute) {
            throw new Exception("attribute `$attributeId` not found");
        }

        return $this->updateAttributeActualValue($attribute, $itemId);
    }

    /**
     * @throws Exception
     */
    private function updateAttributeActualValue(array $attribute, int $itemId): bool
    {
        $actualValue = $this->calcAvgUserValue($attribute, $itemId);

        if ($actualValue['empty']) {
            $actualValue = $this->calcEngineValue($attribute, $itemId);
        }

        if ($actualValue['empty']) {
            $actualValue = $this->calcInheritedValue($attribute, $itemId);
        }

        return $this->setActualValue($attribute, $itemId, $actualValue);
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
    public function updateAllActualValues(): void
    {
        $attributes = $this->getAttributes();

        $select = $this->userValueTable->getSql()->select();
        $select->columns(['item_id'])
            ->quantifier($select::QUANTIFIER_DISTINCT);

        foreach ($this->userValueTable->selectWith($select) as $row) {
            foreach ($attributes as $attribute) {
                if ($attribute['typeId']) {
                    $this->updateAttributeActualValue($attribute, $row['item_id']);
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    public function updateActualValues(int $itemId): void
    {
        foreach ($this->getAttributes() as $attribute) {
            if ($attribute['typeId']) {
                $this->updateAttributeActualValue($attribute, $itemId);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function updateInheritedValues(int $itemId): void
    {
        foreach ($this->getAttributes() as $attribute) {
            if ($attribute['typeId']) {
                $haveValue = $this->haveOwnAttributeValue($attribute['id'], $itemId);
                if (! $haveValue) {
                    $this->updateAttributeActualValue($attribute, $itemId);
                }
            }
        }
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
     * @return mixed|null
     * @throws Exception
     */
    public function getUserValue(int $attributeId, int $itemId, int $userId)
    {
        if (! $itemId) {
            throw new Exception("item_id not set");
        }

        $attribute = $this->getAttribute($attributeId);
        if (! $attribute) {
            throw new Exception("attribute not found");
        }

        $valuesTable = $this->getUserValueDataTable($attribute['typeId']);

        $select = new Sql\Select($valuesTable->getTable());
        $select->columns(['value'])
            ->where([
                'attribute_id' => (int) $attribute['id'],
                'item_id'      => $itemId,
                'user_id'      => $userId,
            ]);

        if ($attribute['isMultiple']) {
            $select->order('ordering');
        }

        $values = [];
        foreach ($valuesTable->selectWith($select) as $row) {
            $values[] = $this->prepareValue($attribute['typeId'], $row['value']);
        }

        if (count($values) <= 0) {
            return null;
        }

        return $attribute['isMultiple'] ? $values : $values[0];
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

    /**
     * @throws Exception
     */
    public function getType(int $typeId): array
    {
        if (! isset($this->types)) {
            $this->types = [];
            foreach ($this->typeTable->select() as $row) {
                $this->types[(int) $row['id']] = [
                    'id'        => (int) $row['id'],
                    'name'      => $row['name'],
                    'element'   => $row['element'],
                    'maxlength' => $row['maxlength'],
                    'size'      => $row['size'],
                ];
            }
        }

        if (! isset($this->types[$typeId])) {
            throw new Exception("Type `$typeId` not found");
        }

        return $this->types[$typeId];
    }

    /**
     * @throws Exception
     */
    public function refreshConflictFlag(int $attributeId, int $itemId): void
    {
        if (! $attributeId) {
            throw new Exception("attributeId not provided");
        }

        if (! $itemId) {
            throw new Exception("itemId not provided");
        }

        $attribute = $this->getAttribute($attributeId);
        if (! $attribute) {
            throw new Exception("Attribute not found");
        }

        $userValueRows = $this->userValueTable->select([
            'attribute_id = ?' => $attribute['id'],
            'item_id = ?'      => $itemId,
        ]);

        $userValues   = [];
        $uniqueValues = [];
        foreach ($userValueRows as $userValueRow) {
            $v                                    = $this->getUserValue(
                $attribute['id'],
                $itemId,
                $userValueRow['user_id']
            );
            $serializedValue                      = serialize($v);
            $uniqueValues[]                       = $serializedValue;
            $userValues[$userValueRow['user_id']] = [
                'value' => $serializedValue,
                'date'  => $userValueRow['update_date'],
            ];
        }

        $uniqueValues = array_unique($uniqueValues);
        $hasConflict  = count($uniqueValues) > 1;

        $valueRow = currentFromResultSetInterface($this->valueTable->select([
            'attribute_id' => $attribute['id'],
            'item_id'      => $itemId,
        ]));

        if (! $valueRow) {
            return;
            //throw new Exception("Value row not found");
        }

        $this->valueTable->update([
            'conflict' => $hasConflict ? 1 : 0,
        ], [
            'attribute_id' => $attribute['id'],
            'item_id'      => $itemId,
        ]);

        $affectedUserIds = [];

        if ($hasConflict) {
            $actualValue = serialize($this->getActualValue($attributeId, $itemId));

            $minDate           = null; // min date of actual value
            $actualValueVoters = 0;
            foreach ($userValues as $userId => $userValue) {
                if ($userValue['value'] === $actualValue) {
                    $actualValueVoters++;
                    if ($minDate === null || $minDate > $userValue['date']) {
                        $minDate = $userValue['date'];
                    }
                }
            }

            foreach ($userValues as $userId => $userValue) {
                $matchActual = $userValue['value'] === $actualValue;
                $conflict    = $matchActual ? -1 : 1;

                if ($actualValueVoters > 1) {
                    if ($matchActual) {
                        $isFirstMatchActual = $userValue['date'] === $minDate;
                        $weight             = $isFirstMatchActual
                            ? self::WEIGHT_FIRST_ACTUAL : self::WEIGHT_SECOND_ACTUAL;
                    } else {
                        $weight = self::WEIGHT_WRONG;
                    }
                } else {
                    $weight = self::WEIGHT_NONE;
                }

                $affectedRows = $this->userValueTable->update([
                    'conflict' => $conflict,
                    'weight'   => $weight,
                ], [
                    'user_id = ?'      => $userId,
                    'attribute_id = ?' => $attributeId,
                    'item_id = ?'      => $itemId,
                ]);

                if ($affectedRows) {
                    $affectedUserIds[] = $userId;
                }
            }
        } else {
            $affectedRows = $this->userValueTable->update([
                'conflict' => 0,
                'weight'   => self::WEIGHT_NONE,
            ], [
                'attribute_id = ?' => $attributeId,
                'item_id = ?'      => $itemId,
            ]);

            if ($affectedRows) {
                $affectedUserIds = array_keys($userValues);
            }
        }

        $this->refreshUserConflicts($affectedUserIds);
    }

    /**
     * @param int|int[] $userId
     */
    public function refreshUserConflicts($userId): void
    {
        $userId = (array) $userId;

        if (count($userId)) {
            $pSelect = 'SELECT sum(weight) FROM attrs_user_values WHERE user_id = users.id AND weight > 0';

            $nSelect = 'SELECT abs(sum(weight)) FROM attrs_user_values WHERE user_id = users.id AND weight < 0';

            $expr = new Sql\Expression(
                '1.5 * ((1 + IFNULL((' . $pSelect . '), 0)) / (1 + IFNULL((' . $nSelect . '), 0)))'
            );

            $this->userModel->getTable()->update([
                'specs_weight' => $expr,
            ], [
                new Sql\Predicate\In('id', $userId),
            ]);
        }
    }

    /**
     * @throws Exception
     */
    public function refreshConflictFlags(): void
    {
        $select = new Sql\Select($this->valueTable->getTable());
        $select
            ->quantifier($select::QUANTIFIER_DISTINCT)
            ->join(
                'attrs_user_values',
                'attrs_values.attribute_id = attrs_user_values.attribute_id '
                    . 'and attrs_values.item_id = attrs_user_values.item_id',
                []
            )
            ->where(['attrs_user_values.conflict']);

        foreach ($this->valueTable->selectWith($select) as $valueRow) {
            $this->refreshConflictFlag($valueRow['attribute_id'], $valueRow['item_id']);
        }
    }

    /**
     * @throws Exception
     */
    public function refreshItemConflictFlags(int $itemId): void
    {
        foreach ($this->userValueTable->select(['item_id' => $itemId]) as $valueRow) {
            $this->refreshConflictFlag($valueRow['attribute_id'], $valueRow['item_id']);
        }
    }

    public function refreshUsersConflictsStat(): void
    {
        $pSelect = 'SELECT sum(weight) FROM attrs_user_values WHERE user_id = users.id AND weight > 0';

        $nSelect = 'SELECT abs(sum(weight)) FROM attrs_user_values WHERE user_id = users.id AND weight < 0';

        $expr = new Sql\Expression(
            '1.5 * ((1 + IFNULL((' . $pSelect . '), 0)) / (1 + IFNULL((' . $nSelect . '), 0)))'
        );

        $this->userModel->getTable()->update([
            'specs_weight' => $expr,
        ], []);
    }

    /**
     * @throws Exception
     */
    private function getUserValueWeight(int $userId): float
    {
        if (! array_key_exists($userId, $this->valueWeights)) {
            $userRow = $this->userModel->getRow($userId);
            if ($userRow) {
                $this->valueWeights[$userId] = $userRow['specs_weight'];
            } else {
                $this->valueWeights[$userId] = 1;
            }
        }

        return $this->valueWeights[$userId];
    }

    public function getUserValueTable(): TableGateway
    {
        return $this->userValueTable;
    }

    public function getAttributeTable(): TableGateway
    {
        return $this->attributeTable;
    }
}
