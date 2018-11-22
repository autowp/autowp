<?php

namespace Application\Service;

use Exception;
use NumberFormatter;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\InputFilter\Input;
use Zend\InputFilter\ArrayInput;
use Zend\Paginator;

use Autowp\User\Model\User;

use Application\ItemNameFormatter;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Picture;
use Application\Model\VehicleType;
use Application\Spec\Table\Car as CarSpecTable;

class SpecificationsService
{
    const ENGINE_ZONE_ID = 5;

    const NULL_VALUE_STR = '-';

    const WEIGHT_NONE          = 0,
          WEIGHT_FIRST_ACTUAL  = 1,
          WEIGHT_SECOND_ACTUAL = 0.1,
          WEIGHT_WRONG         = -1;

    /**
     * @var TableGateway
     */
    private $attributeTable = null;

    /**
     * @var TableGateway
     */
    private $listOptionsTable = null;

    /**
     * @var array
     */
    private $listOptions = [];

    private $listOptionsChilds = [];

    /**
     * @var TableGateway
     */
    private $unitTable = null;

    private $units = null;

    /**
     * @var TableGateway
     */
    private $userValueTable = null;

    private $attributes = null;

    private $childs = null;

    private $zoneAttrs = [];

    /**
     * @var array
     */
    private $carChildsCache = [];

    /**
     * @var array
     */
    private $engineAttributes = null;

    /**
     * @var TableGateway
     */
    private $typeTable;

    /**
     * @var array
     */
    private $types = null;

    /**
     * @var User
     */
    private $userModel;

    private $valueWeights = [];

    private $translator;

    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var ItemParent
     */
    private $itemParent;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var VehicleType
     */
    private $vehicleType;

    /**
     * @var TableGateway
     */
    private $zoneAttributeTable;

    /**
     * @var TableGateway
     */
    private $userValueFloatTable;

    /**
     * @var TableGateway
     */
    private $userValueIntTable;

    /**
     * @var TableGateway
     */
    private $userValueListTable;

    /**
     * @var TableGateway
     */
    private $userValueStringTable;

    /**
     * @var TableGateway
     */
    private $valueTable;

    /**
     * @var TableGateway
     */
    private $valueFloatTable;

    /**
     * @var TableGateway
     */
    private $valueIntTable;

    /**
     * @var TableGateway
     */
    private $valueListTable;

    /**
     * @var TableGateway
     */
    private $valueStringTable;

    public function __construct(
        $translator,
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
        $this->translator = $translator;
        $this->itemNameFormatter = $itemNameFormatter;
        $this->itemModel = $itemModel;
        $this->itemParent = $itemParent;
        $this->picture = $picture;
        $this->vehicleType = $vehicleType;
        $this->userModel = $userModel;

        $this->unitTable = $unitTable;
        $this->listOptionsTable = $listOptionsTable;
        $this->typeTable = $typeTable;
        $this->attributeTable = $attributeTable;
        $this->zoneAttributeTable = $zoneAttributeTable;
        $this->userValueTable = $userValueTable;
        $this->userValueFloatTable = $userValueFloatTable;
        $this->userValueIntTable = $userValueIntTable;
        $this->userValueListTable = $userValueListTable;
        $this->userValueStringTable = $userValueStringTable;
        $this->valueTable = $valueTable;
        $this->valueFloatTable = $valueFloatTable;
        $this->valueIntTable = $valueIntTable;
        $this->valueListTable = $valueListTable;
        $this->valueStringTable = $valueStringTable;
    }

    private function loadUnits()
    {
        if ($this->units === null) {
            $units = [];
            foreach ($this->unitTable->select([]) as $unit) {
                $id = (int)$unit['id'];
                $units[$id] = [
                    'id'   => $id,
                    'name' => $unit['name'],
                    'abbr' => $unit['abbr']
                ];
            }

            $this->units = $units;
        }
    }

    public function getUnits()
    {
        $this->loadUnits();

        return $this->units;
    }

    public function getUnit($id)
    {
        $this->loadUnits();

        $id = (int)$id;

        return isset($this->units[$id]) ? $this->units[$id] : null;
    }

    public function getZoneIdByCarTypeId(int $itemTypeId, array $vehicleTypeIds)
    {
        if ($itemTypeId == Item::ENGINE) {
            return self::ENGINE_ZONE_ID;
        }

        $zoneId = 1;

        if (array_intersect($vehicleTypeIds, [19, 39, 28, 32])) {
            $zoneId = 3;
        }

        return $zoneId;
    }

    private function loadListOptions(array $attributeIds)
    {
        $ids = array_diff($attributeIds, array_keys($this->listOptions));

        if (count($ids)) {
            $select = new Sql\Select($this->listOptionsTable->getTable());
            $select
                ->where(new Sql\Predicate\In('attribute_id', $ids))
                ->order('position');
            $rows = $this->listOptionsTable->selectWith($select);

            foreach ($rows as $row) {
                $aid = (int)$row['attribute_id'];
                $id = (int)$row['id'];
                $pid = (int)$row['parent_id'];
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

    public function getListOptionsArray(int $attributeId): array
    {
        $this->loadListOptions([$attributeId]);

        if (! isset($this->listOptions[$attributeId])) {
            return [];
        }

        return $this->getListOptionsArrayRecursive($attributeId, 0);
    }

    private function getListOptionsArrayRecursive(int $aid, int $parentId)
    {
        $result = [];
        if (isset($this->listOptionsChilds[$aid][$parentId])) {
            foreach ($this->listOptionsChilds[$aid][$parentId] as $childId) {
                $result[] = [
                    'id'   => (int)$childId,
                    'name' => $this->translator->translate($this->listOptions[$aid][$childId])
                ];
                $childOptions = $this->getListOptionsArrayRecursive($aid, $childId);
                foreach ($childOptions as &$value) {
                    $value['name'] = '… ' . $this->translator->translate($value['name']);
                }
                unset($value); // prevent future bugs
                $result = array_merge($result, $childOptions);
            }
        }
        return $result;
    }

    private function getListsOptions(array $attributeIds)
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

    private function getListOptions(int $aid, int $parentId)
    {
        $result = [];
        if (isset($this->listOptionsChilds[$aid][$parentId])) {
            foreach ($this->listOptionsChilds[$aid][$parentId] as $childId) {
                $result[(int)$childId] = $this->translator->translate($this->listOptions[$aid][$childId]);
                $childOptions = $this->getListOptions($aid, $childId);
                foreach ($childOptions as &$value) {
                    $value = '…' . $this->translator->translate($value);
                }
                unset($value); // prevent future bugs
                $result = array_replace($result, $childOptions);
            }
        }
        return $result;
    }

    private function getListOptionsText(int $attributeId, int $id)
    {
        $this->loadListOptions([$attributeId]);

        if (! isset($this->listOptions[$attributeId][$id])) {
            throw new Exception("list option `$id` not found");
        }

        return $this->translator->translate($this->listOptions[$attributeId][$id], 'default');
    }

    public function getFilterSpec(int $attributeId)
    {
        $filters = [];
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
                            'max' => $type['maxlength']
                        ]
                    ];
                }
                break;

            case 2: // int
                $filters = [['name' => 'StringTrim']];
                $validators = [
                    [
                        'name'    => \Application\Validator\Attrs\IsIntOrNull::class,
                        'options' => ['locale' => 'en_US']
                    ]
                ];
                break;

            case 3: // float
                $filters = [['name' => 'StringTrim']];
                $validators = [
                    [
                        'name'    => \Application\Validator\Attrs\IsFloatOrNull::class,
                        'options' => ['locale' => 'en_US']
                    ]
                ];
                break;

            case 4: // textarea
                $filters = [['name' => 'StringTrim']];
                break;

            case 5: // checkbox
                $validators = [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [
                                '',
                                '-',
                                '0',
                                '1'
                            ]
                        ]
                    ]
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
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => $haystack
                        ]
                    ]
                ];
                if ($attribute['isMultiple']) {
                    $inputType = ArrayInput::class;
                }
                break;
        }

        return [
            'type'       => $inputType,
            'required'   => false,
            'filters'    => $filters,
            'validators' => $validators
        ];
    }

    private function loadZone(int $id)
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
            $result[] = (int)$row['attribute_id'];
        }

        $this->zoneAttrs[$id] = $result;

        return $result;
    }

    /**
     * @return SpecificationsService
     */
    private function loadAttributes()
    {
        if ($this->attributes === null) {
            $array = [];
            $childs = [];

            $select = new Sql\Select($this->attributeTable->getTable());
            $select->order('position');

            foreach ($this->attributeTable->selectWith($select) as $row) {
                $id = (int)$row['id'];
                $pid = (int)$row['parent_id'];
                $array[$id] = [
                    'id'          => $id,
                    'name'        => $row['name'],
                    'description' => $row['description'],
                    'typeId'      => (int)$row['type_id'],
                    'unitId'      => (int)$row['unit_id'],
                    'isMultiple'  => $row['multiple'],
                    'precision'   => $row['precision'],
                    'parentId'    => $pid ? $pid : null
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
            $this->childs = $childs;
        }

        return $this;
    }

    /**
     * @param int $id
     * @return NULL|array
     */
    public function getAttribute(int $id)
    {
        $this->loadAttributes();

        return isset($this->attributes[$id]) ? $this->attributes[$id] : null;
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function setUserValue2(int $uid, int $attributeId, int $itemId, $value, bool $empty)
    {
        $attribute = $this->getAttribute($attributeId);
        $somethingChanged = false;

        $userValueDataTable = $this->getUserValueDataTable($attribute['typeId']);

        $userValuePrimaryKey = [
            'attribute_id' => $attribute['id'],
            'item_id'      => $itemId,
            'user_id'      => $uid
        ];

        if ($attribute['isMultiple']) {
            // remove value descriptiors
            $this->userValueTable->delete([
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $itemId,
                'user_id = ?'      => $uid,
            ]);

            // remove values
            $userValueDataTable->delete($userValuePrimaryKey);

            if ($value) {
                if ($value === [null]) {
                    $value = [];
                }

                if ($empty) {
                    $value = [null];
                }

                if (count($value)) {
                    // insert new descriptiors and values
                    $this->userValueTable->insert(array_replace([
                        'add_date'     => new Sql\Expression('NOW()'),
                        'update_date'  => new Sql\Expression('NOW()'),
                    ], $userValuePrimaryKey));
                    $ordering = 1;

                    foreach ($value as $oneValue) {
                        $userValueDataTable->insert(array_replace([
                            'ordering'     => $ordering,
                            'value'        => $oneValue
                        ], $userValuePrimaryKey));

                        $ordering++;
                    }
                }
            }

            $somethingChanged = $this->updateAttributeActualValue($attribute, $itemId);
        } else {
            if (strlen($value) > 0 || $empty) {
                // insert/update value decsriptor
                $userValue = $this->userValueTable->select($userValuePrimaryKey)->current();

                // insert update value
                $userValueData = $userValueDataTable->select($userValuePrimaryKey)->current();

                if ($empty) {
                    $value = null;
                }

                if ($userValueData) {
                    $valueChanged = $value === null
                        ? $userValueData['value'] !== null
                        : $userValueData['value'] != $value;
                } else {
                    $valueChanged = true;
                }

                if (! $userValue || $valueChanged) {
                    if (! $userValue) {
                        $this->userValueTable->insert(array_replace([
                            'add_date'    => new Sql\Expression('NOW()'),
                            'update_date' => new Sql\Expression('NOW()')
                        ], $userValuePrimaryKey));
                    } else {
                        $this->userValueTable->update([
                            'update_date' => new Sql\Expression('NOW()')
                        ], $userValuePrimaryKey);
                    }

                    $set = ['value' => $value];

                    if ($userValueData) {
                        $userValueDataTable->update($set, $userValuePrimaryKey);
                    } else {
                        $userValueDataTable->insert(array_merge($set, $userValuePrimaryKey));
                    }

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

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function setUserValue(int $uid, int $attributeId, int $itemId, $value)
    {
        $attribute = $this->getAttribute($attributeId);
        $somethingChanged = false;

        $userValueDataTable = $this->getUserValueDataTable($attribute['typeId']);

        $userValuePrimaryKey = [
            'attribute_id' => $attribute['id'],
            'item_id'      => $itemId,
            'user_id'      => $uid
        ];

        if ($attribute['isMultiple']) {
            // remove value descriptiors
            $this->userValueTable->delete([
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $itemId,
                'user_id = ?'      => $uid,
            ]);

            // remove values
            $userValueDataTable->delete($userValuePrimaryKey);

            if ($value) {
                $empty = true;
                $valueNot = false;
                foreach ($value as $oneValue) {
                    if ($oneValue) {
                        $empty = false;
                    }

                    if ($oneValue == self::NULL_VALUE_STR) {
                        $valueNot = true;
                    }
                }

                if (! $empty) {
                    // insert new descriptiors and values
                    $this->userValueTable->insert(array_replace([
                        'add_date'     => new Sql\Expression('NOW()'),
                        'update_date'  => new Sql\Expression('NOW()'),
                    ], $userValuePrimaryKey));
                    $ordering = 1;

                    if ($valueNot) {
                        $value = [null];
                    }

                    foreach ($value as $oneValue) {
                        $userValueDataTable->insert(array_replace([
                            'ordering'     => $ordering,
                            'value'        => $oneValue
                        ], $userValuePrimaryKey));

                        $ordering++;
                    }
                }
            }

            $somethingChanged = $this->updateAttributeActualValue($attribute, $itemId);
        } else {
            if (strlen($value) > 0) {
                // insert/update value decsriptor
                $userValue = $this->userValueTable->select($userValuePrimaryKey)->current();

                // insert update value
                $userValueData = $userValueDataTable->select($userValuePrimaryKey)->current();

                if ($value == self::NULL_VALUE_STR) {
                    $value = null;
                }

                if ($userValueData) {
                    $valueChanged = $value === null
                        ? $userValueData['value'] !== null
                        : $userValueData['value'] != $value;
                } else {
                    $valueChanged = true;
                }

                if (! $userValue || $valueChanged) {
                    if (! $userValue) {
                        $this->userValueTable->insert(array_replace([
                            'add_date'    => new Sql\Expression('NOW()'),
                            'update_date' => new Sql\Expression('NOW()')
                        ], $userValuePrimaryKey));
                    } else {
                        $this->userValueTable->update([
                            'update_date' => new Sql\Expression('NOW()')
                        ], $userValuePrimaryKey);
                    }

                    $set = ['value' => $value];

                    if ($userValueData) {
                        $userValueDataTable->update($set, $userValuePrimaryKey);
                    } else {
                        $userValueDataTable->insert(array_merge($set, $userValuePrimaryKey));
                    }

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
        if ($this->engineAttributes) {
            return $this->engineAttributes;
        }

        $select = new Sql\Select($this->attributeTable->getTable());
        $select->columns(['id'])
            ->join('attrs_zone_attributes', 'attrs_attributes.id = attrs_zone_attributes.attribute_id', [])
            ->where(['attrs_zone_attributes.zone_id' => self::ENGINE_ZONE_ID]);

        $result = [];
        foreach ($this->attributeTable->selectWith($select) as $row) {
            $result[] = (int)$row['id'];
        }

        $this->engineAttributes = $result;

        return $result;
    }

    /**
     * @param array $attribute
     * @param int $itemId
     */
    private function propageteEngine($attribute, int $itemId)
    {
        if (! $this->isEngineAttributeId($attribute['id'])) {
            return;
        }

        if (! $attribute['typeId']) {
            return;
        }

        $vehicles = $this->itemModel->getTable()->select([
            'engine_item_id' => $itemId
        ]);

        foreach ($vehicles as $vehicle) {
            $this->updateAttributeActualValue($attribute, $vehicle['id']);
        }
    }

    private function getChildCarIds(int $parentId)
    {
        if (! isset($this->carChildsCache[$parentId])) {
            $this->carChildsCache[$parentId] = $this->itemParent->getChildItemsIds($parentId);
        }

        return $this->carChildsCache[$parentId];
    }

    private function haveOwnAttributeValue(int $attributeId, int $itemId): bool
    {
        return (bool)$this->userValueTable->select([
            'attribute_id' => $attributeId,
            'item_id'      => $itemId
        ])->current();
    }

    private function propagateInheritance($attribute, int $itemId)
    {
        $childIds = $this->getChildCarIds($itemId);

        foreach ($childIds as $childId) {
            // update only if row use inheritance
            $haveValue = $this->haveOwnAttributeValue($attribute['id'], $childId);

            if (! $haveValue) {
                $value = $this->calcInheritedValue($attribute, $childId);
                $changed = $this->setActualValue($attribute, $childId, $value);
                if ($changed) {
                    $this->propagateInheritance($attribute, $childId);
                    $this->propageteEngine($attribute, $childId);
                }
            }
        }
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    private function specPicture($car, $perspectives)
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
                'ancestor_or_self' => $car['id']
            ],
            'order'  => $order,
            'group'  => ['picture_item.perspective_id']
        ]);
    }

    public function getAttributes(array $options = [])
    {
        $defaults = [
            'zone'      => null,
            'parent'    => null,
            'recursive' => false
        ];
        $options = array_merge($defaults, $options);

        $zone = $options['zone'];
        $parent = $options['parent'];
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
                    $ids = [];
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
                    'recursive' => $recursive
                ]);
            }
        }

        return $attributes;
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function getActualValue(int $attribute, int $itemId)
    {
        if (! $itemId) {
            throw new Exception("item_id not set");
        }

        $attribute = $this->getAttribute($attribute);

        $valuesTable = $this->getValueDataTable($attribute['typeId']);

        $select = new Sql\Select($valuesTable->getTable());
        $select->columns(['value'])
            ->where([
                'attribute_id' => $attribute['id'],
                'item_id'      => $itemId
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
            $row = $valuesTable->selectWith($select)->current();

            if ($row) {
                return $row['value'];
            }
        }

        return null;
    }

    /**
     * @param int $attributeId
     * @param int $itemId
     * @param int $userId
     * @throws Exception
     */
    public function deleteUserValue(int $attributeId, int $itemId, int $userId)
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
            'attribute_id = ?' => (int)$attribute['id'],
            'item_id = ?'      => $itemId,
            'user_id = ?'      => $userId
        ]);

        $this->userValueTable->delete([
            'attribute_id = ?' => (int)$attribute['id'],
            'item_id = ?'      => $itemId,
            'user_id = ?'      => $userId
        ]);

        $this->updateActualValue($attribute['id'], $itemId);
    }

    private function loadValues($attributes, int $itemId, string $language)
    {
        $values = [];
        foreach ($attributes as $attribute) {
            $value = $this->getActualValue($attribute['id'], $itemId);
            $valueText = $this->valueToText($attribute, $value, $language);
            $values[$attribute['id']] = $valueText;

            /*if ($valueText === null) {
                // load child values
            }*/

            foreach ($this->loadValues($attribute['childs'], $itemId, $language) as $id => $value) {
                $values[$id] = $value;
            }
        }
        return $values;
    }

    public function specifications($cars, array $options)
    {
        $options = array_merge([
            'contextCarId' => null,
            'language'   => 'en'
        ], $options);

        $language = $options['language'];
        $contextCarId = (int)$options['contextCarId'];

        $topPerspectives = [10, 1, 7, 8, 11, 12, 2, 4, 13, 5];
        $bottomPerspectives = [13, 2, 9, 6, 5];

        $ids = [];
        foreach ($cars as $car) {
            $ids[] = $car['id'];
        }

        $result = [];
        $attributes = [];

        $zoneIds = [];
        foreach ($cars as $car) {
            $vehicleTypeIds = $this->vehicleType->getVehicleTypes($car['id']);
            $zoneId = $this->getZoneIdByCarTypeId($car['item_type_id'], $vehicleTypeIds);

            $zoneIds[$zoneId] = true;
        }

        $zoneMixed = count($zoneIds) > 1;

        if ($zoneMixed) {
            $specsZoneId = null;
        } else {
            $keys = array_keys($zoneIds);
            $specsZoneId = reset($keys);
        }

        $attributes = $this->getAttributes([
            'zone'      => $specsZoneId,
            'recursive' => true,
            'parent'    => 0
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
            $itemId = (int)$car['id'];

            //$values = $this->loadValues($attributes, $itemId);
            $values = isset($actualValues[$itemId]) ? $actualValues[$itemId] : [];

            // append engine name
            if (! (isset($values[$engineNameAttr]) && $values[$engineNameAttr]) && $car['engine_item_id']) {
                $engineRow = $this->itemModel->getTable()->select(['id' => (int)$car['engine_item_id']])->current();
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

            $topPicture = $this->specPicture($car, $topPerspectives);
            $topPictureRequest = null;
            if ($topPicture) {
                $topPictureRequest = $topPicture['image_id'];
            }
            $bottomPicture = $this->specPicture($car, $bottomPerspectives);
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
                'values'               => $values
            ];
        }

        // remove empty attributes
        $this->removeEmpty($attributes, $result);

        // load units
        $this->addUnitsToAttributes($attributes);

        return new CarSpecTable($result, $attributes);
    }

    private function addUnitsToAttributes(&$attributes)
    {
        foreach ($attributes as &$attribute) {
            if ($attribute['unitId']) {
                $attribute['unit'] = $this->getUnit($attribute['unitId']);
            }

            $this->addUnitsToAttributes($attribute['childs']);
        }
    }

    private function removeEmpty(&$attributes, $cars)
    {
        foreach ($attributes as $idx => &$attribute) {
            $this->removeEmpty($attribute['childs'], $cars);

            if (count($attribute['childs']) > 0) {
                $haveValue = true;
            } else {
                $id = $attribute['id'];
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

    public function getValueTable(): TableGateway
    {
        return $this->valueTable;
    }

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

    private function valueToText($attribute, $value, string $language)
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
                return is_null($value) ? null : ($value ? 'да' : 'нет');

            case 6: // select
            case 7: // select
                if ($value) {
                    if (is_array($value)) {
                        $text = [];
                        $nullText = false;
                        foreach ($value as $v) {
                            if ($v === null) {
                                $text[] = null;
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

    private function calcAvgUserValue($attribute, int $itemId)
    {
        $userValuesDataTable = $this->getUserValueDataTable($attribute['typeId']);

        $userValueDataRows = $userValuesDataTable->select([
            'attribute_id' => $attribute['id'],
            'item_id'      => $itemId
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
                'empty' => true
            ];
        }

        $idx = 0;
        $registry = $freshness = $ratios = [];
        foreach ($data as $uid => $valueRows) {
            if ($attribute['isMultiple']) {
                $value = [];
                foreach ($valueRows as $valueRow) {
                    $value[$valueRow['ordering']] = $valueRow['value'];
                }
            } else {
                foreach ($valueRows as $valueRow) {
                    $value = $valueRow['value'];
                }
            }

            $row = $this->userValueTable->select([
                'attribute_id' => $attribute['id'],
                'item_id'      => $itemId,
                'user_id'      => $uid
            ])->current();
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
                $matchRegIdx = $idx;
                $idx++;
            }

            if (! isset($ratios[$matchRegIdx])) {
                $ratios[$matchRegIdx] = 0;
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
        $maxValueIdx = null;
        foreach ($ratios as $idx => $ratio) {
            if (is_null($maxValueIdx)) {
                $maxValueIdx = $idx;
                $maxValueRatio = $ratio;
            } elseif ($maxValueRatio <= $ratio) {
                if ($freshness[$idx] > $freshness[$maxValueIdx]) {
                    $maxValueIdx = $idx;
                    $maxValueRatio = $ratio;
                } else {
                    $maxValueIdx = $idx;
                    $maxValueRatio = $ratio;
                }
            }
        }
        $actualValue = $registry[$maxValueIdx];
        $empty = false;

        return [
            'value' => $actualValue,
            'empty' => $empty
        ];
    }

    /**
     * @param int $attrId
     * @return boolean
     */
    private function isEngineAttributeId(int $attrId)
    {
        return in_array($attrId, $this->getEngineAttributeIds());
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     *
     * @param array $attribute
     * @param int $itemId
     * @return mixed
     */
    private function calcEngineValue($attribute, int $itemId)
    {
        if (! $this->isEngineAttributeId($attribute['id'])) {
            return [
                'empty' => true,
                'value' => null
            ];
        }

        $carRow = $this->itemModel->getTable()->select([
            'id' => $itemId
        ])->current();

        if (! $carRow) {
            return [
                'empty' => true,
                'value' => null
            ];
        }

        if (! $carRow['engine_item_id']) {
            return [
                'empty' => true,
                'value' => null
            ];
        }

        $valueDataTable = $this->getValueDataTable($attribute['typeId']);

        if (! $attribute['isMultiple']) {
            $valueDataRow = $valueDataTable->select([
                'attribute_id' => $attribute['id'],
                'item_id'      => $carRow['engine_item_id'],
                'value IS NOT NULL'
            ])->current();

            if ($valueDataRow) {
                return [
                    'empty' => false,
                    'value' => $valueDataRow['value']
                ];
            } else {
                return [
                    'empty' => true,
                    'value' => null
                ];
            }
        } else {
            $valueDataRows = $valueDataTable->select([
                'attribute_id' => $attribute['id'],
                'item_id'      => $carRow['engine_item_id'],
                'value IS NOT NULL'
            ]);

            if (count($valueDataRows)) {
                $value = [];
                foreach ($valueDataRows as $valueDataRow) {
                    $value[] = $valueDataRow['value'];
                }

                return [
                    'empty' => false,
                    'value' => $value
                ];
            } else {
                return [
                    'empty' => true,
                    'value' => null
                ];
            }
        }
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     */
    private function calcInheritedValue($attribute, int $itemId)
    {
        $actualValue = [
            'empty' => true,
            'value' => null
        ];

        $parentIds = $this->itemParent->getParentIds($itemId);

        $valueDataTable = $this->getValueDataTable($attribute['typeId']);

        if (count($parentIds) > 0) {
            if (! $attribute['isMultiple']) {
                $idx = 0;
                $registry = [];
                $ratios = [];

                $valueDataRows = $valueDataTable->select([
                    'attribute_id' => $attribute['id'],
                    new Sql\Predicate\In('item_id', $parentIds)
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
                        $matchRegIdx = $idx;
                        $idx++;
                    }

                    if (! isset($ratios[$matchRegIdx])) {
                        $ratios[$matchRegIdx] = 0;
                    }
                    $ratios[$matchRegIdx] += 1;
                }

                // select max
                $maxValueRatio = 0;
                $maxValueIdx = null;
                foreach ($ratios as $idx => $ratio) {
                    if (is_null($maxValueIdx)) {
                        $maxValueIdx = $idx;
                        $maxValueRatio = $ratio;
                    } elseif ($maxValueRatio <= $ratio) {
                        $maxValueIdx = $idx;
                        $maxValueRatio = $ratio;
                    }
                }
                if ($maxValueIdx !== null) {
                    $actualValue = [
                        'empty' => false,
                        'value' => $registry[$maxValueIdx]
                    ];
                }
            } else {
                //TODO: multiple attr inheritance
            }
        }

        return $actualValue;
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    private function setActualValue($attribute, int $itemId, array $actualValue)
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
            $valueRow = $this->valueTable->select($primaryKey)->current();
            if (! $valueRow) {
                $this->valueTable->insert(array_replace([
                    'update_date'  => new Sql\Expression('now()')
                ], $primaryKey));
                $somethingChanges = true;
            }

            // value
            if ($attribute['isMultiple']) {
                $affected = $valueDataTable->delete($primaryKey);
                if ($affected > 0) {
                    $somethingChanges = true;
                }

                foreach ($actualValue['value'] as $ordering => $value) {
                    $valueDataTable->insert(array_replace([
                        'ordering' => $ordering,
                        'value'    => $value
                    ], $primaryKey));
                    $somethingChanges = true;
                }
            } else {
                $set = ['value' => $actualValue['value']];
                $row = $valueDataTable->select($primaryKey)->current();
                if (! $row) {
                    $valueDataTable->insert(array_replace($set, $primaryKey));
                    $somethingChanges = true;
                } else {
                    if ($actualValue['value'] === null || $row['value'] === null) {
                        $valueDifferent = $actualValue['value'] !== $row['value'];
                    } else {
                        $valueDifferent = $actualValue['value'] != $row['value'];
                    }
                    if ($valueDifferent) {
                        $affected = $valueDataTable->update($set, $primaryKey);
                        if ($affected > 0) {
                            $somethingChanges = true;
                        }
                    }
                }
            }

            if ($somethingChanges) {
                $this->valueTable->update([
                    'update_date' => new Sql\Expression('now()')
                ], $primaryKey);
            }
        }

        return $somethingChanges;
    }

    public function updateActualValue(int $attributeId, int $itemId)
    {
        $attribute = $this->getAttribute($attributeId);
        return $this->updateAttributeActualValue($attribute, $itemId);
    }

    private function updateAttributeActualValue($attribute, int $itemId)
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
     * @suppress PhanUndeclaredMethod
     * @param int|array $itemId
     * @return boolean|array
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
                $result[(int)$id] = false;
            }

            foreach ($this->valueTable->selectWith($select) as $row) {
                $result[(int)$row['item_id']] = true;
            }

            return $result;
        }

        $select->where(['item_id' => (int)$itemId])
            ->limit(1);

        return (bool)$this->valueTable->selectWith($select)->current();
    }

    public function twinsGroupsHasSpecs(array $groupIds): array
    {
        return $this->hasChildSpecs($groupIds);
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanUndeclaredMethod
     */
    public function getSpecsCount(int $itemId): int
    {
        $select = new Sql\Select($this->valueTable->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where(['item_id' => $itemId]);

        $row = $this->valueTable->selectWith($select)->current();

        return $row ? (int)$row['count'] : 0;
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @param int|array $itemId
     * @return boolean|array
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
                $result[(int)$id] = false;
            }
            foreach ($this->valueTable->selectWith($select) as $row) {
                $result[(int)$row['parent_id']] = true;
            }
            return $result;
        }

        $select->where(['item_parent.parent_id' => $itemId]);
        return (bool) $this->valueTable->selectWith($select)->current();
    }

    public function updateAllActualValues()
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

    public function updateActualValues(int $itemId)
    {
        foreach ($this->getAttributes() as $attribute) {
            if ($attribute['typeId']) {
                $this->updateAttributeActualValue($attribute, $itemId);
            }
        }
    }

    /**
     * @param int $itemId
     */
    public function updateInheritedValues(int $itemId)
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

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     */
    public function getContributors($itemId): array
    {
        if (! $itemId) {
            return [];
        }

        $select = new Sql\Select($this->userValueTable->getTable());
        $select->columns(['user_id', 'c' => new Sql\Expression('COUNT(1)')])
            ->where([new Sql\Predicate\In('item_id', (array)$itemId)])
            ->group('user_id')
            ->order('c desc');

        $result = [];
        foreach ($this->userValueTable->selectWith($select) as $row) {
            $result[(int)$row['user_id']] = (int)$row['c'];
        }

        return $result;
    }

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
                return is_null($value) ? null : ($value ? 1 : 0);

            case 6: // select
            case 7: // tree select
                return is_null($value) ? null : (int)$value;
                break;
        }
        return null;
    }

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
                'attribute_id' => (int)$attribute['id'],
                'item_id'      => $itemId,
                'user_id'      => $userId
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

    public function getUserValue2(int $attributeId, int $itemId, int $userId): array
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
                'attribute_id' => (int)$attribute['id'],
                'item_id'      => $itemId,
                'user_id'      => $userId
            ]);

        if ($attribute['isMultiple']) {
            $select->order('ordering');
        }

        $values = [];
        foreach ($valuesTable->selectWith($select) as $row) {
            $values[] = $this->prepareValue($attribute['typeId'], $row['value']);
        }

        if (count($values) <= 0) {
            return [
                'value' => null,
                'empty' => false
            ];
        }

        if ($attribute['isMultiple']) {
            return [
                'value' => $values,
                'empty' => $values === [null]
            ];
        }

        return [
            'value' => $values[0],
            'empty' => $values[0] === null
        ];
    }

    public function getUserValueText(int $attributeId, int $itemId, int $userId, string $language)
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
                'attribute_id' => (int)$attribute['id'],
                'item_id'      => $itemId,
                'user_id'      => $userId
            ]);

        if ($attribute['isMultiple']) {
            $select->order('ordering');
        }

        $values = [];
        foreach ($valuesTable->selectWith($select) as $row) {
            $values[] = $this->valueToText($attribute, $row['value'], $language);
        }

        if (count($values) > 1) {
            return implode(', ', $values);
        } elseif (count($values) == 1) {
            if ($values[0] === null) {
                return null;
            } else {
                return $values[0];
            }
        }

        return null;
    }

    public function getActualValueText(int $attributeId, int $itemId, string $language)
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
     * @param array $itemIds
     * @return array
     */
    private function getItemsActualValues($itemIds)
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
                $aid = (int)$row['attribute_id'];
                $id = (int)$row['item_id'];
                $value = $this->prepareValue($typeId, $row['value']);
                if (! isset($values[$id])) {
                    $values[$id] = [];
                }

                $attribute = $this->getAttribute($aid);

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

    private function getZoneItemsActualValues(int $zoneId, array $itemIds): array
    {
        if (count($itemIds) <= 0) {
            return [];
        }

        $this->loadZone($zoneId);

        $attributes = $this->getAttributes([
            'zone'   => $zoneId,
            'parent' => null
        ]);

        $requests = [];

        foreach ($attributes as $attribute) {
            $typeId = $attribute['typeId'];
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
                    $aid = (int)$row['attribute_id'];
                    $id = (int)$row['item_id'];
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
     * @param int $typeId
     * @return array
     */
    public function getType(int $typeId)
    {
        if ($this->types === null) {
            $this->types = [];
            foreach ($this->typeTable->select() as $row) {
                $this->types[(int)$row['id']] = [
                    'id'        => (int)$row['id'],
                    'name'      => $row['name'],
                    'element'   => $row['element'],
                    'maxlength' => $row['maxlength'],
                    'size'      => $row['size']
                ];
            }
        }

        if (! isset($this->types[$typeId])) {
            throw new Exception("Type `$typeId` not found");
        }

        return $this->types[$typeId];
    }

    public function refreshConflictFlag(int $attributeId, int $itemId)
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

        $userValues = [];
        $uniqueValues = [];
        foreach ($userValueRows as $userValueRow) {
            $v = $this->getUserValue($attribute['id'], $itemId, $userValueRow['user_id']);
            $serializedValue = serialize($v);
            $uniqueValues[] = $serializedValue;
            $userValues[$userValueRow['user_id']] = [
                'value' => $serializedValue,
                'date'  => $userValueRow['update_date']
            ];
        }

        $uniqueValues = array_unique($uniqueValues);
        $hasConflict = count($uniqueValues) > 1;

        $valueRow = $this->valueTable->select([
            'attribute_id' => $attribute['id'],
            'item_id'      => $itemId,
        ])->current();

        if (! $valueRow) {
            return;
            //throw new Exception("Value row not found");
        }

        $this->valueTable->update([
            'conflict' => $hasConflict ? 1 : 0
        ], [
            'attribute_id' => $attribute['id'],
            'item_id'      => $itemId,
        ]);

        $affectedUserIds = [];

        if ($hasConflict) {
            $actualValue = serialize($this->getActualValue($attributeId, $itemId));

            $minDate = null; // min date of actual value
            $actualValueVoters = 0;
            foreach ($userValues as $userId => $userValue) {
                if ($userValue['value'] == $actualValue) {
                    $actualValueVoters++;
                    if ($minDate === null || $minDate > $userValue['date']) {
                        $minDate = $userValue['date'];
                    }
                }
            }

            foreach ($userValues as $userId => $userValue) {
                $matchActual = $userValue['value'] == $actualValue;
                $conflict = $matchActual ? -1 : 1;

                if ($actualValueVoters > 1) {
                    if ($matchActual) {
                        $isFirstMatchActual = $userValue['date'] == $minDate;
                        $weight = $isFirstMatchActual ? self::WEIGHT_FIRST_ACTUAL : self::WEIGHT_SECOND_ACTUAL;
                    } else {
                        $weight = self::WEIGHT_WRONG;
                    }
                } else {
                    $weight = self::WEIGHT_NONE;
                }

                $affectedRows = $this->userValueTable->update([
                    'conflict' => $conflict,
                    'weight'   => $weight
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
                'weight'   => self::WEIGHT_NONE
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
     * @suppress PhanDeprecatedFunction
     */
    public function refreshUserConflicts($userId)
    {
        $userId = (array)$userId;

        if (count($userId)) {
            $pSelect = 'SELECT sum(weight) FROM attrs_user_values WHERE user_id = users.id AND weight > 0';

            $nSelect = 'SELECT abs(sum(weight)) FROM attrs_user_values WHERE user_id = users.id AND weight < 0';

            $expr = new Sql\Expression(
                '1.5 * ((1 + IFNULL((' . $pSelect . '), 0)) / (1 + IFNULL((' . $nSelect . '), 0)))'
            );

            $this->userModel->getTable()->update([
                'specs_weight' => $expr,
            ], [
                new Sql\Predicate\In('id', $userId)
            ]);
        }
    }

    public function refreshConflictFlags()
    {
        $select = new Sql\Select($this->valueTable->getTable());
        $select
            ->quantifier($select::QUANTIFIER_DISTINCT)
            ->join(
                'attrs_user_values',
                'attrs_values.attribute_id = attrs_user_values.attribute_id ' .
                    'and attrs_values.item_id = attrs_user_values.item_id',
                []
            )
            ->where(['attrs_user_values.conflict']);

        foreach ($this->valueTable->selectWith($select) as $valueRow) {
            $this->refreshConflictFlag($valueRow['attribute_id'], $valueRow['item_id']);
        }
    }

    public function refreshItemConflictFlags(int $itemId)
    {
        foreach ($this->userValueTable->select(['item_id' => $itemId]) as $valueRow) {
            $this->refreshConflictFlag($valueRow['attribute_id'], $valueRow['item_id']);
        }
    }

    public function getConflicts(int $userId, $filter, int $page, int $perPage)
    {
        $userId = (int)$userId;

        $select = new Sql\Select($this->valueTable->getTable());
        $select
            ->join(
                'attrs_user_values',
                'attrs_values.attribute_id = attrs_user_values.attribute_id ' .
                    'and attrs_values.item_id = attrs_user_values.item_id',
                []
            )
            ->where(['attrs_user_values.user_id' => $userId])
            ->order('attrs_values.update_date desc');

        if ($filter == 'minus-weight') {
            $select->where(['attrs_user_values.weight < 0']);
        } elseif ($filter == 0) {
            $select->where(['attrs_values.conflict']);
        } elseif ($filter > 0) {
            $select->where(['attrs_user_values.conflict > 0']);
        } elseif ($filter < 0) {
            $select->where(['attrs_user_values.conflict < 0']);
        }

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->valueTable->getAdapter())
        );

        $paginator
            ->setItemCountPerPage($perPage)
            ->setCurrentPageNumber($page);

        $conflicts = [];
        foreach ($paginator->getCurrentItems() as $valueRow) {
            $attribute = $this->getAttribute($valueRow['attribute_id']);

            $unit = null;
            if ($attribute['unitId']) {
                $unit = $this->getUnit($attribute['unitId']);
            }

            $attributeName = [];
            $cAttr = $attribute;
            do {
                $attributeName[] = $this->translator->translate($cAttr['name']);
                $cAttr = $this->getAttribute((int)$cAttr['parentId']);
            } while ($cAttr);

            $conflicts[] = [
                'attribute_id' => (int)$valueRow['attribute_id'],
                'item_id'      => (int)$valueRow['item_id'],
                'attribute'    => implode(' / ', array_reverse($attributeName)),
                'unit'         => $unit
            ];
        }

        return [
            'conflicts' => $conflicts,
            'paginator' => $paginator
        ];
    }

    public function refreshUserConflictsStat()
    {
        $select = new Sql\Select($this->userValueTable->getTable());
        $select->columns(['user_id']);

        $userIds = [];
        foreach ($this->userValueTable->selectWith($select) as $row) {
            $userIds[] = (int)$row['user_id'];
        }

        $this->refreshUserConflicts($userIds);
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function refreshUsersConflictsStat()
    {
        $pSelect = 'SELECT sum(weight) FROM attrs_user_values WHERE user_id = users.id AND weight > 0';

        $nSelect = 'SELECT abs(sum(weight)) FROM attrs_user_values WHERE user_id = users.id AND weight < 0';

        $expr = new Sql\Expression(
            '1.5 * ((1 + IFNULL((' . $pSelect . '), 0)) / (1 + IFNULL((' . $nSelect . '), 0)))'
        );

        $this->userModel->getTable()->update([
            'specs_weight' => $expr
        ], []);
    }

    private function getUserValueWeight(int $userId)
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
