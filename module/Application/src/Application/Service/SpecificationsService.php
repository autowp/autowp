<?php

namespace Application\Service;

use Exception;
use NumberFormatter;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Paginator;

use Autowp\Commons\Db\Table\Row;
use Autowp\User\Model\DbTable\User;

use Application\Form\AttrsZoneAttributes as AttrsZoneAttributesForm;
use Application\ItemNameFormatter;
use Application\Model\DbTable;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Picture;
use Application\Model\VehicleType;
use Application\Spec\Table\Car as CarSpecTable;

use Zend_Db_Expr;

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
    private $userTable;

    /**
     * @var array
     */
    private $users = [];

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
     * @var DbTable\Picture
     */
    private $pictureTable;

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
        DbTable\Picture $pictureTable,
        VehicleType $vehicleType,
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
        $this->pictureTable = $pictureTable;
        $this->vehicleType = $vehicleType;

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

    /**
     * @return User
     */
    private function getUserTable()
    {
        return $this->userTable
            ? $this->userTable
            : $this->userTable = new User();
    }

    /**
     * @param int $userId
     * @return array
     */
    private function getUser($userId)
    {
        if (! isset($this->users[$userId])) {
            $userRow = $this->getUserTable()->find($userId)->current();
            $this->users[$userId] = $userRow;
        }

        return $this->users[$userId];
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

    private function zoneIdByCarTypeId(int $itemTypeId, array $vehicleTypeIds)
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

    private function walkTreeR(int $zoneId, callable $callback)
    {
        $this->loadAttributes();
        $this->loadZone($zoneId);

        return $this->walkTreeRStep($zoneId, 0, $callback);
    }

    private function walkTreeRStep(int $zoneId, int $parent, callable $callback)
    {
        $attributes = $this->getAttributes([
            'parent' => $parent,
            'zone'   => $zoneId
        ]);

        $result = [];

        foreach ($attributes as $attribute) {
            $key = 'attr_' . $attribute['id'];
            $haveChilds = isset($this->childs[$attribute['id']]);
            if ($haveChilds) {
                $result[$key] = $this->walkTreeRStep($zoneId, $attribute['id'], $callback);
            } else {
                $result[$key] = $callback($attribute);
            }
        }

        return $result;
    }

    private function walkTreeStep(int $zoneId, int $parent, callable $callback)
    {
        $attributes = $this->getAttributes([
            'parent' => $parent,
            'zone'   => $zoneId
        ]);

        $result = [];

        foreach ($attributes as $attribute) {
            $key = 'attr_' . $attribute['id'];
            $haveChilds = isset($this->childs[$attribute['id']]);
            if ($haveChilds) {
                $result = array_replace($result, $this->walkTreeStep($zoneId, $attribute['id'], $callback));
            } else {
                $result[$key] = $callback($attribute);
            }
        }

        return $result;
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
                $result[$childId] = $this->translator->translate($this->listOptions[$aid][$childId]);
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

    public function getFormData(int $itemId, int $zoneId, \Autowp\Commons\Db\Table\Row $user, $language)
    {
        $zoneUserValues = $this->getZoneUsersValues($zoneId, $itemId);

        // fetch values dates
        $dates = [];
        if (count($zoneUserValues)) {
            $valueDescRows = $this->userValueTable->select([
                new Sql\Predicate\In('attribute_id', array_keys($zoneUserValues)),
                'item_id' => $itemId,
            ]);
            foreach ($valueDescRows as $valueDescRow) {
                $date = Row::getDateTimeByColumnType('timestamp', $valueDescRow['update_date']);
                $dates[$valueDescRow['attribute_id']][$valueDescRow['user_id']] = $date;
            }
        }

        $currentUserValues = [];
        $allValues = [];
        foreach ($zoneUserValues as $attributeId => $users) {
            foreach ($users as $userId => $value) {
                $date = null;
                if (isset($dates[$attributeId][$userId])) {
                    $date = $dates[$attributeId][$userId];
                }

                $attribute = $this->getAttribute($attributeId);
                if (! $attribute) {
                    throw new Exception("Attribute `$attributeId` not found");
                }

                $allValues[$attributeId][] = [
                    'user'  => $this->getUser($userId),
                    'value' => $this->valueToText($attribute, $value, $language),
                    'date'  => $date
                ];

                if ($userId == $user['id']) {
                    $currentUserValues[$attributeId] = $value;
                }
            }
        }

        $zoneActualValues = $this->getZoneActualValues($zoneId, $itemId);
        $actualValues = [];
        foreach ($zoneActualValues as $attributeId => $value) {
            $attribute = $this->getAttribute($attributeId);
            if (! $attribute) {
                throw new Exception("Attribute `$attributeId` not found");
            }

            $actualValues[$attributeId] = $this->valueToText($attribute, $value, $language);
        }

        return [
            'allValues'          => $allValues,
            'actualValues'       => $actualValues,
            'editableAttributes' => array_keys($currentUserValues)
        ];
    }

    private function buildForm($attributes, int $zoneId, bool $editOnlyMode, $multioptions)
    {
        $elements = [];
        $inputFilters = [];

        foreach ($attributes as $attribute) {
            $subAttributes = $this->getAttributes([
                'parent' => $attribute['id'],
                'zone'   => $zoneId
            ]);

            $nodeName = 'attr_' . $attribute['id'];
            $options = [
                'label' => $attribute['name'],
            ];
            $filters = [];
            $validators = [];
            if (count($subAttributes)) {
                $subFormSpec = $this->buildForm($subAttributes, $zoneId, $editOnlyMode, $multioptions);
                $elements[] = [
                    'spec' => [
                        'name'       => $nodeName,
                        'type'       => 'Fieldset',
                        'options'    => $options,
                        'attributes' => [
                            'id' => 'subform-' . $attribute['id']
                        ],
                        'elements'   => $subFormSpec['elements']
                    ]
                ];
                $inputFilters[$nodeName] = array_replace([
                    'type' => 'Zend\InputFilter\InputFilter'
                ], $subFormSpec['input_filter']);
            } else {
                $readonly = false;
                if ($editOnlyMode) {
                    $readonly = ! in_array($attribute['id'], $editOnlyMode);
                }

                $attributes = [
                    'class'     => 'input-sm form-control',
                    'disabled'  => $readonly ? 'disabled' : null,
                    'data-unit' => $attribute['unitId'],
                    'data-desc' => $attribute['description'],
                    'id'        => 'attr-' . $attribute['id']
                ];

                $type = null;
                if ($attribute['typeId']) {
                    $type = $this->getType($attribute['typeId']);
                }

                if ($type) {
                    if ($type['maxlength']) {
                        $attributes['maxlength'] = $type['maxlength'];
                    }
                    if ($type['size']) {
                        $attributes['size'] = $type['size'];
                    }

                    switch ($type['id']) {
                        case 1: // string
                            $filters = [['name' => 'StringTrim']];
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
                            $options['options'] = [
                                ''  => '—',
                                '-' => 'specifications/no-value-text',
                                '0' => 'specifications/boolean/false',
                                '1' => 'specifications/boolean/true'
                            ];
                            break;

                        case 6: // select
                        case 7: // treeselect
                            $elementOptions = [
                                ''  => '—',
                                '-' => 'specifications/no-value-text',
                            ];
                            if (isset($multioptions[$attribute['id']])) {
                                $elementOptions = array_replace($elementOptions, $multioptions[$attribute['id']]);
                            }
                            $options['value_options'] = $elementOptions;
                            break;
                    }
                }

                $elementType = $type['element'];
                if ($type['element'] == 'select' && $attribute['isMultiple']) {
                    $elementType = 'Select';
                    $attributes['multiple'] = true;
                }

                $elements[] = [
                    'spec' => [
                        'name'       => $nodeName,
                        'type'       => $elementType,
                        'options'    => $options,
                        'attributes' => $attributes
                    ]
                ];

                $inputFilters[$nodeName] = [
                    'required'   => false,
                    'filters'    => $filters,
                    'validators' => $validators
                ];
            }
        }

        return [
            'elements'     => $elements,
            'input_filter' => $inputFilters
        ];
    }

    /**
     * @param int $itemId
     * @param int $zoneId
     * @param \Autowp\Commons\Db\Table\Row $user
     * @param array $options
     * @return AttrsZoneAttributesForm
     */
    private function getForm(int $itemId, int $zoneId, $user, array $options)
    {
        $multioptions = $this->getListsOptions($this->loadZone($zoneId));

        $options = array_replace($options, [
            'multioptions' => $multioptions,
        ]);

        $attributes = $this->getAttributes([
            'parent' => 0,
            'zone'   => $zoneId
        ]);

        $formSpec = $this->buildForm($attributes, $zoneId, $options['editOnlyMode'], $multioptions);

        $factory = new \Zend\Form\Factory();
        $form = $factory->create([
            'type'         => 'Zend\Form\Form',
            'attributes'   => [
                'method' => 'post'
            ],
            'elements'     => $formSpec['elements'],
            'input_filter' => $formSpec['input_filter']
        ]);
        $form->prepareElement($form);

        $currentUserValues = $this->getZoneUserValues($zoneId, $itemId, $user['id']);

        //$form = new AttrsZoneAttributesForm(null, $options);
        $formValues = $this->walkTreeR($zoneId, function ($attribute) use ($currentUserValues) {
            if (array_key_exists($attribute['id'], $currentUserValues)) {
                $value = $currentUserValues[$attribute['id']];
                if (is_array($value)) {
                    foreach ($value as $oneValue) {
                        if ($oneValue === null) {
                            return [self::NULL_VALUE_STR];
                        }
                    }
                    return $value;
                } else {
                    return $value === null ? self::NULL_VALUE_STR : $value;
                }
            } else {
                return null;
            }
        });

        $form->populateValues($formValues);

        return $form;
    }

    public function getCarForm(
        $car,
        \Autowp\Commons\Db\Table\Row $user,
        array $options,
        string $language
    ) {
        $vehicleTypeIds = $this->vehicleType->getVehicleTypes($car['id']);

        $zoneId = $this->zoneIdByCarTypeId($car['item_type_id'], $vehicleTypeIds);

        return [
            'form' => $this->getForm($car['id'], $zoneId, $user, $options),
            'data' => $this->getFormData($car['id'], $zoneId, $user, $language)
        ];
    }

    private function collectFormData(int $zoneId, $attributes, $values)
    {
        $result = [];
        foreach ($attributes as $attribute) {
            $id = (int)$attribute['id'];
            $value = $values['attr_' . $id];

            $subAttributes = $this->getAttributes([
                'zone'   => $zoneId,
                'parent' => $id
            ]);

            if (count($subAttributes)) {
                $subvalues = $this->collectFormData($zoneId, $subAttributes, $value);
                $result = array_replace($result, $subvalues);
            } else {
                $result[$id] = $value;
            }
        }

        return $result;
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
    private function getAttribute(int $id)
    {
        $this->loadAttributes();

        return isset($this->attributes[$id]) ? $this->attributes[$id] : null;
    }

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

    public function saveCarAttributes(
        $car,
        array $values,
        \Autowp\Commons\Db\Table\Row $user
    ) {
        $vehicleTypeIds = $this->vehicleType->getVehicleTypes($car['id']);

        $zoneId = $this->zoneIdByCarTypeId($car['item_type_id'], $vehicleTypeIds);

        $attributes = $this->getAttributes([
            'zone'   => $zoneId,
            'parent' => 0
        ]);

        $linearValues = $this->collectFormData($zoneId, $attributes, $values);

        foreach ($linearValues as $attributeId => $value) {
            $this->setUserValue(
                $user['id'],
                $attributeId,
                $car['id'],
                $value
            );
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

    private function specPicture($car, $perspectives)
    {
        $pictureTableAdapter = $this->pictureTable->getAdapter();

        $order = [];
        if ($perspectives) {
            foreach ($perspectives as $pid) {
                $order[] = new Zend_Db_Expr(
                    $pictureTableAdapter->quoteInto('picture_item.perspective_id = ? DESC', $pid)
                );
            }
        } else {
            $order[] = 'pictures.id desc';
        }
        return $this->pictureTable->fetchRow(
            $this->pictureTable->select(true)
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $car['id'])
                ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
                ->order($order)
                ->limit(1)
        );
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

        if ($recursive) {
            $attributes = [];
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
                $attributes = $this->attributes;
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

    public function getActualValueRangeText($attributeId, array $itemId, string $language)
    {
        $attribute = $this->getAttribute($attributeId);

        $range = $this->getActualValueRange($attributeId, $itemId);
        if ($range['min'] !== null) {
            $range['min'] = $this->valueToText($attribute, $range['min'], $language);
        }
        if ($range['max'] !== null) {
            $range['max'] = $this->valueToText($attribute, $range['max'], $language);
        }

        if ($attribute['unitId']) {
            $range['unit'] = $this->getUnit($attribute['unitId']);
        }

        return $range;
    }

    public function getActualValueRange(int $attributeId, array $itemId)
    {
        if (count($itemId) <= 0) {
            throw new Exception("Empty set");
        }

        $attribute = $this->getAttribute($attributeId);

        $numericTypes = [2, 3];

        if (! in_array($attribute['typeId'], $numericTypes)) {
            throw new Exception("Range only for numeric types");
        }


        $valuesTable = $this->getValueDataTable($attribute['typeId']);

        $filter = [
            'attribute_id' => $attribute['id'],
            new Sql\Predicate\In('item_id', $itemId)
        ];

        $min = $max = null;

        foreach ($valuesTable->selectWith($filter) as $row) {
            $value = $row['value'];
            if ($min === null || $value < $min) {
                $min = $value;
            }

            if ($max === null || $value > $max) {
                $max = $value;
            }
        }

        return [
            'min' => $min,
            'max' => $max
        ];
    }

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
            $zoneId = $this->zoneIdByCarTypeId($car['item_type_id'], $vehicleTypeIds);

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
                $topPictureRequest = $this->pictureTable->getFormatRequest($topPicture);
            }
            $bottomPicture = $this->specPicture($car, $bottomPerspectives);
            $bottomPictureRequest = null;
            if ($bottomPicture) {
                $bottomPictureRequest = $this->pictureTable->getFormatRequest($bottomPicture);
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
            /*$user = $uTable->find($uid)->current();
            if (!$user) {
                throw new Exception('User not found');
            }*/

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

    public function getSpecsCount(int $itemId): int
    {
        $select = new Sql\Select($this->valueTable->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where(['item_id' => $itemId]);

        $row = $this->valueTable->selectWith($select)->current();

        return $row ? (int)$row['count'] : 0;
    }

    /**
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

    /**
     * @throws Exception
     */
    private function getZoneUserValues(int $zoneId, int $itemId, int $userId): array
    {
        if (! $itemId) {
            throw new Exception("item_id not set");
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
            foreach ($multiples as $isMultiple => $ids) {
                $valuesTable = $this->getUserValueDataTable($typeId);

                $select = new Sql\Select($valuesTable->getTable());

                $select->columns(['attribute_id', 'value'])
                    ->where([
                        new Sql\Predicate\In('attribute_id', $ids),
                        'item_id' => $itemId,
                        'user_id' => $userId
                    ]);

                if ($isMultiple) {
                    $select->order('ordering');
                }

                foreach ($valuesTable->selectWith($select) as $row) {
                    $aid = (int)$row['attribute_id'];
                    $value = $this->prepareValue($typeId, $row['value']);
                    if ($isMultiple) {
                        if (! isset($values[$aid])) {
                            $values[$aid] = [];
                        }
                        $values[$aid][] = $value;
                    } else {
                        $values[$aid] = $value;
                    }
                }
            }
        }

        return $values;
    }

    /**
     * @param int $zoneId
     * @param int $itemId
     * @param int $userId
     * @throws Exception
     * @return array
     */
    private function getZoneUsersValues(int $zoneId, int $itemId)
    {
        if (! $itemId) {
            throw new Exception("item_id not set");
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
            foreach ($multiples as $isMultiple => $ids) {
                $valuesTable = $this->getUserValueDataTable($typeId);

                $select = new Sql\Select($valuesTable->getTable());
                $select->columns(['attribute_id', 'user_id', 'value'])
                    ->where([
                        new Sql\Predicate\In('attribute_id', $ids),
                        'item_id' => $itemId
                    ]);

                if ($isMultiple) {
                    $select->order('ordering');
                }

                foreach ($valuesTable->selectWith($select) as $row) {
                    $aid = (int)$row['attribute_id'];
                    $uid = (int)$row['user_id'];
                    $value = $this->prepareValue($typeId, $row['value']);
                    if (! isset($values[$aid])) {
                        $values[$aid] = [];
                    }
                    if ($isMultiple) {
                        if (! isset($values[$aid][$uid])) {
                            $values[$aid][$uid] = [];
                        }
                        $values[$aid][$uid][] = $value;
                    } else {
                        $values[$aid][$uid] = $value;
                    }
                }
            }
        }

        return $values;
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

    private function getZoneActualValues(int $zoneId, int $itemId): array
    {
        if (! $itemId) {
            throw new Exception("item_id not set");
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
                    'item_id' => $itemId
                ]);

                if ($isMultiple) {
                    $select->order('ordering');
                }

                foreach ($valueDataTable->selectWith($select) as $row) {
                    $aid = (int)$row['attribute_id'];
                    $value = $this->prepareValue($typeId, $row['value']);
                    if ($isMultiple) {
                        if (! isset($values[$aid])) {
                            $values[$aid] = [];
                        }
                        $values[$aid][] = $value;
                    } else {
                        $values[$aid] = $value;
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

    public function refreshUserConflicts($userId)
    {
        $userId = (array)$userId;

        if (count($userId)) {
            $pSelect = 'SELECT sum(weight) FROM attrs_user_values WHERE user_id = users.id AND weight > 0';

            $nSelect = 'SELECT abs(sum(weight)) FROM attrs_user_values WHERE user_id = users.id AND weight < 0';

            $expr = new Zend_Db_Expr(
                '1.5 * ((1 + IFNULL((' . $pSelect . '), 0)) / (1 + IFNULL((' . $nSelect . '), 0)))'
            );

            $this->getUserTable()->update([
                'specs_weight' => $expr,
            ], [
                'id IN (?)' => $userId
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

    public function getConflicts(int $userId, $filter, int $page, int $perPage, string $language)
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
            // other users values
            $userValueRows = $this->userValueTable->select([
                'attribute_id' => $valueRow['attribute_id'],
                'item_id'      => $valueRow['item_id'],
                'user_id != ?' => $userId
            ]);

            $values = [];
            foreach ($userValueRows as $userValueRow) {
                $values[] = [
                    'value'  => $this->getUserValueText(
                        $userValueRow['attribute_id'],
                        $userValueRow['item_id'],
                        $userValueRow['user_id'],
                        $language
                    ),
                    'userId' => $userValueRow['user_id']
                ];
            }

            // my value
            $userValueRow = $this->userValueTable->select([
                'attribute_id' => $valueRow['attribute_id'],
                'item_id'      => $valueRow['item_id'],
                'user_id'      => $userId
            ])->current();
            $value = null;
            if ($userValueRow) {
                $value = $this->getUserValueText(
                    $userValueRow['attribute_id'],
                    $userValueRow['item_id'],
                    $userValueRow['user_id'],
                    $language
                );
            }

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
                'itemId'     => $valueRow['item_id'],
                'attribute'  => implode(' / ', array_reverse($attributeName)),
                'unit'       => $unit,
                'values'     => $values,
                'value'      => $value
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

    public function refreshUsersConflictsStat()
    {
        $pSelect = 'SELECT sum(weight) FROM attrs_user_values WHERE user_id = users.id AND weight > 0';

        $nSelect = 'SELECT abs(sum(weight)) FROM attrs_user_values WHERE user_id = users.id AND weight < 0';

        $expr = new Zend_Db_Expr(
            '1.5 * ((1 + IFNULL((' . $pSelect . '), 0)) / (1 + IFNULL((' . $nSelect . '), 0)))'
        );

        $this->getUserTable()->update([
            'specs_weight' => $expr
        ], []);
    }

    private function getUserValueWeight(int $userId)
    {
        if (! array_key_exists($userId, $this->valueWeights)) {
            $userRow = $this->getUserTable()->find($userId)->current();
            if ($userRow) {
                $this->valueWeights[$userId] = $userRow['specs_weight'];
            } else {
                $this->valueWeights[$userId] = 1;
            }
        }

        return $this->valueWeights[$userId];
    }
}
