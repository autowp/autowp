<?php

namespace Application\Service;

use Application\Form\AttrsZoneAttributes as AttrsZoneAttributesForm;
use Application\Model\DbTable\Engine;
use Application\Model\DbTable\EngineRow;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\User;
use Application\Model\DbTable\User\Row as UserRow;
use Application\Model\DbTable\Vehicle\ParentTable as VehicleParent;
use Application\Model\DbTable\Vehicle\Row as VehicleRow;
use Application\Model\DbTable\Vehicle\Type as VehicleType;
use Application\Paginator\Adapter\Zend1DbTableSelect;
use Application\Spec\Table\Car as CarSpecTable;
use Application\Spec\Table\Engine as EngineSpecTable;

use Attrs_Attributes;
use Attrs_List_Options;
use Attrs_Types;
use Attrs_Values;
use Attrs_Values_Float;
use Attrs_Values_Int;
use Attrs_Values_List;
use Attrs_Values_String;
use Attrs_Units;
use Attrs_User_Values;
use Attrs_User_Values_Int;
use Attrs_User_Values_Float;
use Attrs_User_Values_List;
use Attrs_User_Values_String;
use Attrs_Zones;
use Cars;

use Exception;
use NumberFormatter;

use Zend_Db_Expr;

class SpecificationsService
{
    const ITEM_TYPE_CAR = 1;
    const ITEM_TYPE_ENGINE = 3;

    const ENGINE_ZONE_ID = 5;

    const NULL_VALUE_STR = '-';

    const WEIGHT_NONE          =  0,
          WEIGHT_FIRST_ACTUAL  =  1,
          WEIGHT_SECOND_ACTUAL =  0.1,
          WEIGHT_WRONG         = -1;


    protected $zones = null;

    /**
     * @var Attrs_Attributes
     */
    protected $attributeTable = null;

    /**
     * @var Attrs_List_Options
     */
    protected $listOptionsTable = null;

    /**
     * @var array
     */
    private $listOptions = [];

    private $listOptionsChilds = [];

    /**
     * @var Attrs_Units
     */
    protected $unitTable = null;

    protected $userValueDataTables = [];

    protected $units = null;

    /**
     * @var Attrs_User_Values
     */
    protected $userValueTable = null;

    protected $attributes = null;

    protected $childs = null;

    protected $zoneAttrs = [];

    /**
     * @var Cars
     */
    protected $carTable = null;

    /**
     * @var VehicleParent
     */
    protected $carParentTable = null;

    /**
     * @var array
     */
    protected $carChildsCache = [];

    /**
     * @var array
     */
    protected $engineChildsCache = [];

    /**
     * @var Attrs_Values
     */
    protected $valueTable = null;

    /**
     * @var array
     */
    protected $engineAttributes = null;

    /**
     * @var Engine
     */
    protected $engineTable = null;

    /**
     * @var Attrs_Types
     */
    protected $typeTable;

    /**
     * @var array
     */
    protected $types = null;

    /**
     * @var User
     */
    protected $userTable;

    /**
     * @var array
     */
    protected $users = [];

    protected $valueWeights = [];

    private $translator;

    public function __construct($translator = null)
    {
        $this->translator = $translator;
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
    private function _getUser($userId)
    {
        if (!isset($this->users[$userId])) {
            $userRow = $this->getUserTable()->find($userId)->current();
            $this->users[$userId] = $userRow;
        }

        return $this->users[$userId];
    }

    /**
     * @return Attrs_Types
     */
    private function getTypeTable()
    {
        return $this->typeTable
            ? $this->typeTable
            : $this->typeTable = new Attrs_Types();
    }

    /**
     * @return Attrs_Values
     */
    protected function getValueTable()
    {
        return $this->valueTable
            ? $this->valueTable
            : $this->valueTable = new Attrs_Values();
    }

    /**
     * @return VehicleParent
     */
    protected function getCarParentTable()
    {
        return $this->carParentTable
            ? $this->carParentTable
            : $this->carParentTable = new VehicleParent();
    }

    /**
     * @return Engine
     */
    protected function getEngineTable()
    {
        return $this->engineTable
            ? $this->engineTable
            : $this->engineTable = new Engine();
    }

    /**
     * @return Cars
     */
    protected function getCarTable()
    {
        return $this->carTable
            ? $this->carTable
            : $this->carTable = new Cars();
    }

    protected function getAttributeTable()
    {
        return $this->attributeTable
            ? $this->attributeTable
            : $this->attributeTable = new Attrs_Attributes();
    }

    protected function getUserValueTable()
    {
        return $this->userValueTable
            ? $this->userValueTable
            : $this->userValueTable = new Attrs_User_Values();
    }

    protected function getListOptionsTable()
    {
        return $this->listOptionsTable
            ? $this->listOptionsTable
            : $this->listOptionsTable = new Attrs_List_Options();
    }

    protected function getUnitTable()
    {
        return $this->unitTable
            ? $this->unitTable
            : $this->unitTable = new Attrs_Units();
    }

    protected function _getZone($id)
    {
        if ($this->zones === null) {
            $zoneTable = new Attrs_Zones();
            $this->zones = [];
            foreach ($zoneTable->fetchAll() as $zone) {
                $this->zones[$zone->id] = $zone;
            }
        }

        if (!isset($this->zones[$id])) {
            throw new Exception("Zone `$id` not found");
        }

        return $this->zones[$id];
    }

    public function getUnit($id)
    {
        if ($this->units === null) {
            $units = [];
            foreach ($this->getUnitTable()->fetchAll() as $unit) {
                $units[$unit->id] = [
                    'id'   => (int)$unit->id,
                    'name' => $unit->name,
                    'abbr' => $unit->abbr
                ];
            }

            $this->units = $units;
        }

        $id = (int)$id;

        return isset($this->units[$id]) ? $this->units[$id] : null;
    }

    protected function _zoneIdByCarTypeId($carTypeId)
    {
        switch ($carTypeId) {
            case 19: // bus
            case 39:
            case 28:
            case 32:
                $zoneId = 3;
                break;

            default:
                $zoneId = 1;
        }

        return $zoneId;
    }

    private function _walkTreeR($zoneId, Callable $callback)
    {
        $this->_loadAttributes();
        $this->loadZone($zoneId);

        return $this->_walkTreeRStep($zoneId, 0, $callback);
    }

    private function _walkTreeRStep($zoneId, $parent, Callable $callback)
    {
        $attributes = $this->getAttributes([
            'parent' => (int)$parent,
            'zone'   => $zoneId
        ]);

        $result = [];

        foreach ($attributes as $attribute) {
            $key = 'attr_' . $attribute['id'];
            $haveChilds = isset($this->childs[$attribute['id']]);
            if ($haveChilds) {
                $result[$key] = $this->_walkTreeRStep($zoneId, $attribute['id'], $callback);
            } else {
                $result[$key] = $callback($attribute);
            }
        }

        return $result;
    }

    private function _walkTree($zoneId, Callable $callback)
    {
        $this->_loadAttributes();
        $this->loadZone($zoneId);

        return $this->_walkTreeStep($zoneId, 0, $callback);
    }

    private function _walkTreeStep($zoneId, $parent, Callable $callback)
    {
        $attributes = $this->getAttributes([
            'parent' => (int)$parent,
            'zone'   => $zoneId
        ]);

        $result = [];

        foreach ($attributes as $attribute) {
            $key = 'attr_' . $attribute['id'];
            $haveChilds = isset($this->childs[$attribute['id']]);
            if ($haveChilds) {
                $result = array_replace($result, $this->_walkTreeStep($zoneId, $attribute['id'], $callback));
            } else {
                $result[$key] = $callback($attribute);
            }
        }

        return $result;
    }

    private function _loadListOptions(array $attributeIds)
    {
        $ids = array_diff($attributeIds, array_keys($this->listOptions));

        if (count($ids)) {
            $rows = $this->getListOptionsTable()->fetchAll([
                'attribute_id IN (?)' => $ids
            ], 'position');

            foreach ($rows as $row) {
                $aid = (int)$row->attribute_id;
                $id = (int)$row->id;
                $pid = (int)$row->parent_id;
                if (!isset($this->listOptions[$aid])) {
                    $this->listOptions[$aid] = [];
                }
                $this->listOptions[$aid][$id] = $row->name;
                if (!isset($this->listOptionsChilds[$aid][$pid])) {
                    $this->listOptionsChilds[$aid][$pid] = [$id];
                } else {
                    $this->listOptionsChilds[$aid][$pid][] = $id;
                }
            }
        }
    }

    private function _getListsOptions(array $attributeIds)
    {
        $this->_loadListOptions($attributeIds);

        $result = [];
        foreach ($attributeIds as $aid) {
            if (isset($this->listOptions[$aid])) {
                $result[$aid] = $this->_getListOptions($aid, 0);
            }
        }

        return $result;
    }

    private function _getListOptions($aid, $parentId)
    {
        $parentId = (int)$parentId;

        $result = [];
        if (isset($this->listOptionsChilds[$aid][$parentId])) {
            foreach ($this->listOptionsChilds[$aid][$parentId] as $childId) {
                $result[$childId] = $this->translator->translate($this->listOptions[$aid][$childId]);
                $childOptions = $this->_getListOptions($aid, $childId);
                foreach ($childOptions as &$value) {
                    $value = '…' . $this->translator->translate($value);
                }
                unset($value); // prevent future bugs
                $result = array_replace($result, $childOptions);
            }
        }
        return $result;
    }

    private function getListOptionsText($attributeId, $id)
    {
        $this->_loadListOptions([$attributeId]);

        if (!isset($this->listOptions[$attributeId][$id])) {
            throw new Exception("list option `$id` not found");
        }

        return $this->translator->translate($this->listOptions[$attributeId][$id], 'default');
    }

    public function getFormData($itemId, $zoneId, UserRow $user, $language)
    {
        $zone = $this->_getZone($zoneId);
        $itemTypeId = $zone->item_type_id;

        $userValueTable = $this->getUserValueTable();
        $zoneUserValues = $this->getZoneUsersValues($zoneId, $itemId);

        // fetch values dates
        $dates = [];
        if (count($zoneUserValues)) {
            $valueDescRows = $userValueTable->fetchAll([
                'attribute_id IN (?)' => array_keys($zoneUserValues),
                'item_id = ?'         => $itemId,
                'item_type_id = ?'    => $itemTypeId,
            ]);
            foreach ($valueDescRows as $valueDescRow) {
                $dates[$valueDescRow->attribute_id][$valueDescRow->user_id] = $valueDescRow->getDateTime('update_date');
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

                $attribute = $this->_getAttribute($attributeId);
                if (!$attribute) {
                    throw new Exception("Attribute `$attributeId` not found");
                }

                $allValues[$attributeId][] = [
                    'user'  => $this->_getUser($userId),
                    'value' => $this->valueToText($attribute, $value, $language),
                    'date'  => $date
                ];

                if ($userId == $user->id) {
                    $currentUserValues[$attributeId] = $value;
                }
            }
        }

        $zoneActualValues = $this->getZoneActualValues($zoneId, $itemId);
        $actualValues = [];
        foreach ($zoneActualValues as $attributeId => $value) {
            $attribute = $this->_getAttribute($attributeId);
            if (!$attribute) {
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

    private function buildForm($attributes, $zoneId, $editOnlyMode, $multioptions)
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
                    $readonly = !in_array($attribute['id'], $editOnlyMode);
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

        return $result = [
            'elements'     => $elements,
            'input_filter' => $inputFilters
        ];;
    }

    /**
     * @param int $itemId
     * @param int $zoneId
     * @param UserRow $user
     * @param array $options
     * @return AttrsZoneAttributesForm
     */
    private function getForm($itemId, $zoneId, $user, array $options)
    {
        $multioptions = $this->_getListsOptions($this->loadZone($zoneId));

        $zone = $this->_getZone($zoneId);

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

        $currentUserValues = $this->getZoneUserValues($zoneId, $itemId, $user->id);

        //$form = new AttrsZoneAttributesForm(null, $options);
        $formValues = $this->_walkTreeR($zoneId, function($attribute) use ($currentUserValues) {
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

    /**
     * @param VehicleRow $car
     * @param UserRow $user
     * @param array $options
     * @return array
     */
    public function getCarForm(VehicleRow $car, UserRow $user, array $options, $language)
    {
        $zoneId = $this->_zoneIdByCarTypeId($car->car_type_id);
        return [
            'form' => $this->getForm($car->id, $zoneId, $user, $options),
            'data' => $this->getFormData($car->id, $zoneId, $user, $language)
        ];
    }

    /**
     * @param EngineRow $engine
     * @param UserRow $user
     * @param array $options
     * @return array
     */
    public function getEngineForm(EngineRow $engine, UserRow $user, array $options, $language)
    {
        $zoneId = 5;
        return [
            'form' => $this->getForm($engine->id, $zoneId, $user, $options),
            'data' => $this->getFormData($engine->id, $zoneId, $user, $language)
        ];
    }

    private function collectFormData($zoneId, $attributes, $values)
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

    private function loadZone($id)
    {
        $id = (int)$id;
        if (!isset($this->zoneAttrs[$id])) {
            $db = $this->getAttributeTable()->getAdapter();
            $this->zoneAttrs[$id] = $db->fetchCol(
                $db->select()
                    ->from('attrs_zone_attributes', 'attribute_id')
                    ->where('zone_id = ?', $id)
                    ->order('position')
            );
        }

        return $this->zoneAttrs[$id];
    }

    /**
     * @return SpecificationsService
     */
    protected function _loadAttributes()
    {
        if ($this->attributes === null) {
            $array = [];
            $childs = [];
            foreach ($this->getAttributeTable()->fetchAll(null, 'position') as $row) {
                $id = (int)$row->id;
                $pid = (int)$row->parent_id;
                $array[$id] = [
                    'id'          => $id,
                    'name'        => $row->name,
                    'description' => $row->description,
                    'typeId'      => (int)$row->type_id,
                    'unitId'      => (int)$row->unit_id,
                    'isMultiple'  => $row->isMultiple(),
                    'precision'   => $row->precision,
                    'parentId'    => $pid ? $pid : null
                ];
                if (!isset($childs[$pid])) {
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
    protected function _getAttribute($id)
    {
        $this->_loadAttributes();

        $id = (int)$id;
        return isset($this->attributes[$id]) ? $this->attributes[$id] : null;
    }

    public function setUserValue($uid, $attributeId, $itemTypeId, $itemId, $value)
    {
        $attribute = $this->_getAttribute($attributeId);
        $somethingChanged = false;

        $userValueTable = $this->getUserValueTable();
        $userValueDataTable = $this->getUserValueDataTable($attribute['typeId']);

        if ($attribute['isMultiple']) {

            // remove value descriptiors
            $userValues = $userValueTable->fetchAll([
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $itemId,
                'item_type_id = ?' => $itemTypeId,
                'user_id = ?'      => $uid,
            ]);
            foreach ($userValues as $userValue) {
                $userValue->delete();
            }
            // remove values
            $userValueDataRows = $userValueDataTable->fetchAll([
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $itemId,
                'item_type_id = ?' => $itemTypeId,
                'user_id = ?'      => $uid
            ]);
            foreach ($userValueDataRows as $userValueDataRow) {
                $userValueDataRow->delete();
            }

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

                if (!$empty) {

                    // insert new descriptiors and values
                    $userValueTable->insert([
                        'attribute_id' => $attribute['id'],
                        'item_id'      => $itemId,
                        'item_type_id' => $itemTypeId,
                        'user_id'      => $uid,
                        'add_date'     => new Zend_Db_Expr('NOW()'),
                        'update_date'  => new Zend_Db_Expr('NOW()'),
                    ]);
                    $ordering = 1;

                    if ($valueNot) {
                        $value = [null];
                    }

                    foreach ($value as $oneValue) {
                        $userValueDataTable->insert([
                            'attribute_id' => $attribute['id'],
                            'item_id'      => $itemId,
                            'item_type_id' => $itemTypeId,
                            'user_id'      => $uid,
                            'ordering'     => $ordering,
                            'value'        => $oneValue
                        ]);

                        $ordering++;
                    }
                }

            }

            $somethingChanged = $this->_updateActualValue($attribute, $itemTypeId, $itemId);

        } else {

            if (strlen($value) > 0) {
                // insert/update value decsriptor
                $userValue = $userValueTable->fetchRow([
                    'attribute_id = ?' => $attribute['id'],
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId,
                    'user_id = ?'      => $uid
                ]);

                // insert update value
                $userValueData = $userValueDataTable->fetchRow([
                    'attribute_id = ?' => $attribute['id'],
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId,
                    'user_id = ?'      => $uid
                ]);

                if ($value == self::NULL_VALUE_STR) {
                    $value = null;
                }

                if ($userValueData) {
                    $valueChanged = $value === null ? $userValueData->value !== null : $userValueData->value != $value;
                }  else {
                    $valueChanged = true;
                }

                if (!$userValue || $valueChanged) {

                    if (!$userValue) {
                        $userValue = $userValueTable->createRow([
                            'attribute_id' => $attribute['id'],
                            'item_id'      => $itemId,
                            'item_type_id' => $itemTypeId,
                            'user_id'      => $uid,
                            'add_date'     => new Zend_Db_Expr('NOW()')
                        ]);
                    }

                    $userValue->setFromArray([
                        'update_date' => new Zend_Db_Expr('NOW()')
                    ]);
                    $userValue->save();

                    if (!$userValueData) {
                        $userValueData = $userValueDataTable->fetchNew();
                        $userValueData->setFromArray([
                            'attribute_id' => $attribute['id'],
                            'item_id'      => $itemId,
                            'item_type_id' => $itemTypeId,
                            'user_id'      => $uid
                        ]);
                    }

                    $userValueData->value = $value;
                    $userValueData->save();

                    $somethingChanged = $this->_updateActualValue($attribute, $itemTypeId, $itemId);
                }

            } else {

                $needUpdate = false;
                // delete value descriptor
                $userValue = $userValueTable->fetchRow([
                    'attribute_id = ?' => $attribute['id'],
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId,
                    'user_id = ?'      => $uid,
                ]);
                if ($userValue) {
                    $userValue->delete();
                    $needUpdate = true;
                }
                // remove value
                $userValueData = $userValueDataTable->fetchRow([
                    'attribute_id = ?' => $attribute['id'],
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId,
                    'user_id = ?'      => $uid,
                ]);
                if ($userValueData) {
                    $userValueData->delete();
                    $needUpdate = true;
                }
                if ($needUpdate) {
                    $somethingChanged = $this->_updateActualValue($attribute, $itemTypeId, $itemId);
                }
            }
        }

        if ($somethingChanged) {
            $this->_propagateInheritance($attribute, $itemTypeId, $itemId);

            $this->_propageteEngine($attribute, $itemTypeId, $itemId);

            $this->refreshConflictFlag($attribute['id'], $itemTypeId, $itemId);
        }
    }

    /**
     * @param VehicleRow $car
     * @param array $values
     * @param UserRow $user
     */
    public function saveCarAttributes(VehicleRow $car, array $values, UserRow $user)
    {
        $zoneId = $this->_zoneIdByCarTypeId($car->car_type_id);
        $zone = $this->_getZone($zoneId);

        $attributes = $this->getAttributes([
            'zone'   => $zoneId,
            'parent' => 0
        ]);

        $linearValues = $this->collectFormData($zoneId, $attributes, $values);

        foreach ($linearValues as $attributeId => $value) {
            $this->setUserValue(
                $user->id,
                $attributeId,
                $zone->item_type_id,
                $car->id,
                $value
            );
        }

    }

    /**
     * @param EngineRow $engine
     * @param array $values
     * @param UserRow $user
     */
    public function saveEngineAttributes(EngineRow $engine, array $values, UserRow $user)
    {
        $zoneId = 5;
        $zone = $this->_getZone($zoneId);

        $attributes = $this->getAttributes([
            'zone'   => $zoneId,
            'parent' => 0
        ]);

        $linearValues = $this->collectFormData($zoneId, $attributes, $values);

        foreach ($linearValues as $attributeId => $value) {
            $this->setUserValue(
                $user->id,
                $attributeId,
                $zone->item_type_id,
                $engine->id,
                $value
            );
        }

    }

    protected function _getEngineAttributeIds()
    {
        if (!$this->engineAttributes) {
            $table = $this->getAttributeTable();
            $db = $table->getAdapter();

            $this->engineAttributes = $db->fetchCol(
                $db->select()
                    ->from($table->info('name'), 'id')
                    ->join('attrs_zone_attributes', 'attrs_attributes.id = attrs_zone_attributes.attribute_id', null)
                    ->where('attrs_zone_attributes.zone_id = ?', self::ENGINE_ZONE_ID)
            );
        }

        return $this->engineAttributes;
    }

    /**
     * @param array $attribute
     * @param int $itemTypeId
     * @param int $parentId
     */
    protected function _propageteEngine($attribute, $itemTypeId, $itemId)
    {
        if ($itemTypeId != self::ITEM_TYPE_ENGINE) {
            return;
        }

        if (!$this->_isEngineAttributeId($attribute['id'])) {
            return;
        }

        if (!$attribute['typeId']) {
            return;
        }

        $carRows = $this->getCarTable()->fetchAll([
            'engine_id = ?' => $itemId
        ]);

        foreach ($carRows as $carRow) {
            $this->_updateActualValue($attribute, self::ITEM_TYPE_CAR, $carRow->id);
        }
    }

    /**
     * @param array $attribute
     * @param int $itemTypeId
     * @param int $parentId
     */
    protected function _getChildCarIds($parentId)
    {
        if (!isset($this->carChildsCache[$parentId])) {
            $carParentTable = $this->getCarParentTable();
            $db = $carParentTable->getAdapter();
            $this->carChildsCache[$parentId] = $db->fetchCol(
                $db->select()
                    ->from($carParentTable->info('name'), 'car_id')
                    ->where('parent_id = ?', $parentId)
            );
        }

        return $this->carChildsCache[$parentId];
    }

    /**
     * @param array $attribute
     * @param int $itemTypeId
     * @param int $itemId
     */

    protected function _getChildEngineIds($parentId)
    {
        if (!isset($this->engineChildsCache[$parentId])) {
            $engineTable = $this->getEngineTable();
            $db = $engineTable->getAdapter();
            $this->engineChildsCache[$parentId] = $db->fetchCol(
                $db->select()
                    ->from($engineTable->info('name'), 'id')
                    ->where('parent_id = ?', $parentId)
            );
        }

        return $this->engineChildsCache[$parentId];
    }

    private function haveOwnAttributeValue($attributeId, $itemTypeId, $itemId)
    {
        return (bool)$this->getUserValueTable()->fetchRow([
            'attribute_id = ?' => (int)$attributeId,
            'item_type_id = ?' => (int)$itemTypeId,
            'item_id = ?'      => (int)$itemId
        ]);
    }

    protected function _propagateInheritance($attribute, $itemTypeId, $itemId)
    {
        if ($itemTypeId == 1) {

            $childIds = $this->_getChildCarIds($itemId);

            foreach ($childIds as $childId) {
                // update only if row use inheritance
                $haveValue = $this->haveOwnAttributeValue($attribute['id'], $itemTypeId, $childId);

                if (!$haveValue) {

                    $value = $this->_calcInheritedValue($attribute, $itemTypeId, $childId);
                    $changed = $this->_setActualValue($attribute, $itemTypeId, $childId, $value);
                    if ($changed) {
                        $this->_propagateInheritance($attribute, $itemTypeId, $childId);
                    }
                }
            }

        } else if ($itemTypeId == 3) {

            $childIds = $this->_getChildEngineIds($itemId);

            foreach ($childIds as $childId) {
                // update only if row use inheritance
                $haveValue = $this->haveOwnAttributeValue($attribute['id'], $itemTypeId, $childId);

                if (!$haveValue) {

                    $value = $this->_calcInheritedValue($attribute, $itemTypeId, $childId);
                    $changed = $this->_setActualValue($attribute, $itemTypeId, $childId, $value);

                    if ($changed) {
                        $this->_propagateInheritance($attribute, $itemTypeId, $childId);
                        $this->_propageteEngine($attribute, $itemTypeId, $childId);
                    }
                }
            }
        }
    }

    protected function _specEnginePicture($engine)
    {
        $pictureTable = new Picture();

        return $pictureTable->fetchRow(
            $pictureTable->select(true)
                ->where('pictures.type = ?', Picture::ENGINE_TYPE_ID)
                ->where('pictures.engine_id = ?', $engine['id'])
                ->where('pictures.status in (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
                ->order('pictures.id desc')
                ->limit(1)
        );
    }

    private function specPicture($car, $perspectives)
    {
        $pictureTable = new Picture();
        $pictureTableAdapter = $pictureTable->getAdapter();

        $order = [];
        if ($perspectives) {
            foreach ($perspectives as $pid) {
                $order[] = new Zend_Db_Expr($pictureTableAdapter->quoteInto('pictures.perspective_id = ? DESC', $pid));
            }
        } else {
            $order[] = 'pictures.id desc';
        }
        return $pictureTable->fetchRow(
            $pictureTable->select(true)
                ->where('pictures.type = ?', Picture::VEHICLE_TYPE_ID)
                ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                ->where('car_parent_cache.parent_id = ?', $car->id)
                ->where('pictures.status in (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
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

        $this->_loadAttributes();

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

    public function getActualValueRangeText($attributeId, array $itemId, $itemTypeId, $language)
    {
        $attribute = $this->_getAttribute($attributeId);

        $range = $this->getActualValueRange($attributeId, $itemId, $itemTypeId);
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

    public function getActualValueRange($attributeId, array $itemId, $itemTypeId)
    {
        if (count($itemId) <= 0) {
            throw new Exception("Empty set");
        }

        $attribute = $this->_getAttribute($attributeId);

        $numericTypes = [2, 3];

        if (!in_array($attribute['typeId'], $numericTypes)) {
            throw new Exception("Range only for numeric types");
        }


        $valuesTable = $this->getValueDataTable($attribute['typeId']);
        if (!$valuesTable) {
            return null;
        }

        $select = $valuesTable->select(true)
            ->where('attribute_id = ?', $attribute['id'])
            ->where('item_id IN (?)', $itemId)
            ->where('item_type_id = ?', (int)$itemTypeId);


        $min = $max = null;

        foreach ($valuesTable->fetchAll($select) as $row) {
            $value = $row->value;
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

    public function getActualValue($attribute, $itemId, $itemTypeId)
    {
        if (!$itemId) {
            throw new Exception("Item_id not set");
        }

        if ($attribute instanceof Attrs_Attributes_Row) {
            $attribute = $this->_getAttribute($attribute->id);
        } elseif (is_numeric($attribute)) {
            $attribute = $this->_getAttribute($attribute);
        }

        $valuesTable = $this->getValueDataTable($attribute['typeId']);
        if (!$valuesTable) {
            return null;
        }

        $select = $valuesTable->select(true)
            ->where('attribute_id = ?', $attribute['id'])
            ->where('item_id = ?', $itemId)
            ->where('item_type_id = ?', (int)$itemTypeId);

        if ($attribute['isMultiple']) {

            $select->order('ordering');

            $rows = $valuesTable->fetchAll($select);

            $values = [];
            foreach ($rows as $row) {
                $values[] = $row->value;
            }

            if (count($values)) {
                return $values;
            }

        } else {
            $row = $valuesTable->fetchRow($select);

            if ($row) {
                return $row->value;
            }
        }

        return null;
    }

    /**
     * @param int $attributeId
     * @param int $itemTypeId
     * @param int $itemId
     * @param int $userId
     * @throws Exception
     */
    public function deleteUserValue($attributeId, $itemTypeId, $itemId, $userId)
    {
        if (!$itemId) {
            throw new Exception("Item_id not set");
        }

        $attribute = $this->_getAttribute($attributeId);
        if (!$attribute) {
            throw new Exception("attribute not found");
        }

        $valueTable = $this->getUserValueTable();
        $row = $valueTable->fetchRow([
            'attribute_id = ?' => (int)$attribute['id'],
            'item_id = ?'      => (int)$itemId,
            'item_type_id = ?' => (int)$itemTypeId,
            'user_id = ?'      => (int)$userId
        ]);

        $valueDataTable = $this->getUserValueDataTable($attribute['typeId']);
        if (!$valueDataTable) {
            throw new Exception("Failed to allocate data table");
        }

        $dataRows = $valueDataTable->fetchAll([
            'attribute_id = ?' => (int)$attribute['id'],
            'item_id = ?'      => (int)$itemId,
            'item_type_id = ?' => (int)$itemTypeId,
            'user_id = ?'      => (int)$userId
        ]);

        foreach ($dataRows as $dataRow) {
            $dataRow->delete();
        }

        $row->delete();

        $this->updateActualValue($attribute['id'], $itemTypeId, $itemId);
    }

    protected function _loadValues($attributes, $itemId, $itemTypeId, $language)
    {
        $values = [];
        foreach ($attributes as $attribute) {
            $value = $this->getActualValue($attribute, $itemId, $itemTypeId);
            $valueText = $this->valueToText($attribute, $value, $language);
            $values[$attribute['id']] = $valueText;

            /*if ($valueText === null) {
                // load child values
            }*/

            foreach ($this->_loadValues($attribute['childs'], $itemId, $itemTypeId, $language) as $id => $value) {
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

        $carTypeTable = new VehicleType();
        $attributeTable = $this->getAttributeTable();
        $carParentTable = new VehicleParent();

        $ids = [];
        foreach ($cars as $car) {
            $ids[] = $car->id;
        }

        $result = [];
        $attributes = [];

        $zoneIds = [];
        foreach ($cars as $car) {
            $zoneId = $this->_zoneIdByCarTypeId($car->car_type_id);

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

        $engineTable = $this->getEngineTable();
        $engineNameAttr = 100;

        $carIds = [];
        foreach ($cars as $car) {
            $carIds[] = $car->id;
        }

        if ($specsZoneId) {
            $this->_loadListOptions($this->zoneAttrs[$specsZoneId]);
            $actualValues = $this->_getZoneItemsActualValues($specsZoneId, $carIds);
        } else {
            $actualValues = $this->_getItemsActualValues($carIds, self::ITEM_TYPE_CAR);
        }

        foreach ($actualValues as &$itemActualValues) {
            foreach ($itemActualValues as $attributeId => &$value) {
                $attribute = $this->_getAttribute($attributeId);
                if (!$attribute) {
                    throw new Exception("Attribute `$attributeId` not found");
                }
                $value = $this->valueToText($attribute, $value, $language);
            }
            unset($value); // prevent future bugs
        }
        unset($itemActualValues); // prevent future bugs

        foreach ($cars as $car) {

            $itemId = (int)$car->id;

            $carType = $carTypeTable->find($car->car_type_id)->current();

            //$values = $this->_loadValues($attributes, $itemId, self::ITEM_TYPE_CAR);
            $values = isset($actualValues[$itemId]) ? $actualValues[$itemId] : [];

            // append engine name
            if (!(isset($values[$engineNameAttr]) && $values[$engineNameAttr]) && $car->engine_id) {
                $engineRow = $engineTable->find($car->engine_id)->current();
                if ($engineRow) {
                    $values[$engineNameAttr] = $engineRow->caption;
                }
            }

            $carParentName = null;
            if ($contextCarId) {
                $carParentRow = $carParentTable->fetchRow([
                    'car_id = ?'    => $car->id,
                    'parent_id = ?' => $contextCarId
                ]);
                if ($carParentRow) {
                    $carParentName = $carParentRow->name;
                }
            }

            $result[] = [
                'id'               => $itemId,
                'name'             => $carParentName ? $carParentName : $car->getFullName($language),
                'beginYear'        => $car->begin_year,
                'endYear'          => $car->end_year,
                'produced'         => $car->produced,
                'produced_exactly' => $car->produced_exactly,
                'topPicture'       => $this->specPicture($car, $topPerspectives),
                'bottomPicture'    => $this->specPicture($car, $bottomPerspectives),
                'carType'          => $carType ? $carType->name : null,
                'values'           => $values
            ];
        }

        // remove empty attributes
        $this->_removeEmpty($attributes, $result);

        // load units
        $this->_addUnitsToAttributes($attributes);

        return new CarSpecTable($result, $attributes);
    }

    public function engineSpecifications($engines, array $options)
    {
        $options = array_merge([
            'language' => 'en'
        ], $options);

        $language = $options['language'];

        $attributeTable = $this->getAttributeTable();

        $result = [];
        $attributes = [];

        $attributes = $this->getAttributes([
            'zone'      => self::ENGINE_ZONE_ID,
            'recursive' => true,
            'parent'    => 0
        ]);

        foreach ($engines as $engine) {
            $result[] = [
                'id'      => $engine['id'],
                'name'    => $engine['name'],
                'picture' => $this->_specEnginePicture($engine),
                'values'  => $this->_loadValues($attributes, $engine['id'], self::ITEM_TYPE_ENGINE, $options['language'])
            ];
        }

        // remove empty attributes
        $this->_removeEmpty($attributes, $result);

        // load units
        $this->_addUnitsToAttributes($attributes);

        return new EngineSpecTable($result, $attributes);
    }

    protected function _addUnitsToAttributes(&$attributes)
    {
        foreach ($attributes as &$attribute) {
            if ($attribute['unitId']) {
                $attribute['unit'] = $this->getUnit($attribute['unitId']);
            }

            $this->_addUnitsToAttributes($attribute['childs']);
        }
    }

    protected function _removeEmpty(&$attributes, $cars)
    {
        foreach ($attributes as $idx => &$attribute) {
            $this->_removeEmpty($attribute['childs'], $cars);

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

            if (!$haveValue) {
                unset($attributes[$idx]);
            }
        }
    }

    public function getValueDataTable($type)
    {
        if (!isset($this->_valueDataTables[$type])) {
            $this->_valueDataTables[$type] = $this->_createValueDataTable($type);
        }

        return $this->_valueDataTables[$type];
    }


    protected function _createValueDataTable($type)
    {
        switch ($type) {
            case 1: // string
                return new Attrs_Values_String();

            case 2: // int
                return new Attrs_Values_Int();

            case 3: // float
                return new Attrs_Values_Float();

            case 4: // textarea
                throw new Exception("Unexpected type 4");
                //return new Attrs_Values_Text();

            case 5: // checkbox
                return new Attrs_Values_Int();

            case 6: // select
                return new Attrs_Values_List();

            case 7: // select
                return new Attrs_Values_List();
        }
        return null;
    }


    public function getUserValueDataTable($type)
    {
        if (!isset($this->userValueDataTables[$type])) {
            $this->userValueDataTables[$type] = $this->_createUserValueDataTable($type);
        }

        return $this->userValueDataTables[$type];
    }

    protected function _createUserValueDataTable($type)
    {
        switch ($type) {
            case 1: // string
                return new Attrs_User_Values_String();

            case 2: // int
                return new Attrs_User_Values_Int();

            case 3: // float
                return new Attrs_User_Values_Float();

            case 4: // textarea
                throw new Exception("Unexpected type 4");
                //return new Attrs_User_Values_Text();

            case 5: // checkbox
                return new Attrs_User_Values_Int();

            case 6: // select
                return new Attrs_User_Values_List();

            case 7: // select
                return new Attrs_User_Values_List();

            default:
                throw new Exception("Unexpected type `$type`");
        }
        return null;
    }

    protected function valueToText($attribute, $value, $language)
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

    protected function _calcAvgUserValue($attribute, $itemTypeId, $itemId)
    {
        $userValuesTable = $this->getUserValueTable();
        $userValuesDataTable = $this->getUserValueDataTable($attribute['typeId']);

        $userValueDataRows = $userValuesDataTable->fetchAll([
            'attribute_id = ?' => $attribute['id'],
            'item_id = ?'      => $itemId,
            'item_type_id = ?' => $itemTypeId,
        ]);
        if (count($userValueDataRows)) {

            // group by users
            $data = [];
            foreach ($userValueDataRows as $userValueDataRow) {
                $uid = $userValueDataRow->user_id;
                if (!isset($data[$uid])) {
                    $data[$uid] = [];
                }
                $data[$uid][] = $userValueDataRow;
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
                        $value[$valueRow->ordering] = $valueRow->value;
                    }
                } else {
                    foreach ($valueRows as $valueRow) {
                        $value = $valueRow->value;
                    }
                }

                $row = $userValuesTable->fetchRow([
                    'attribute_id = ?' => $attribute['id'],
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId,
                    'user_id = ?'      => $uid
                ]);
                if (!$row) {
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

                if (!isset($ratios[$matchRegIdx])) {
                    $ratios[$matchRegIdx] = 0;
                    $freshness[$matchRegIdx] = null;
                }
                $ratios[$matchRegIdx] += $this->getUserValueWeight($uid);
                if ($freshness[$matchRegIdx] < $row->update_date) {
                    $freshness[$matchRegIdx] = $row->update_date;
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
        } else {
            $actualValue = null;
            $empty = true;
        }

        return [
            'value' => $actualValue,
            'empty' => $empty
        ];
    }

    /**
     * @param int $attrId
     * @return boolean
     */
    protected function _isEngineAttributeId($attrId)
    {
        return in_array($attrId, $this->_getEngineAttributeIds());
    }

    /**
     * @param array $attribute
     * @param int $itemTypeId
     * @param int $itemId
     * @return mixed
     */
    protected function _calcEngineValue($attribute, $itemTypeId, $itemId)
    {
        if ($itemTypeId != self::ITEM_TYPE_CAR) {
            return [
                'empty' => true,
                'value' => null
            ];
        }

        if (!$this->_isEngineAttributeId($attribute['id'])) {
            return [
                'empty' => true,
                'value' => null
            ];
        }

        $carRow = $this->getCarTable()->fetchRow([
            'id = ?' => $itemId
        ]);

        if (!$carRow) {
            return [
                'empty' => true,
                'value' => null
            ];
        }

        if (!$carRow->engine_id) {
            return [
                'empty' => true,
                'value' => null
            ];
        }

        $valueDataTable = $this->getValueDataTable($attribute['typeId']);

        if (!$attribute['isMultiple']) {

            $valueDataRow = $valueDataTable->fetchRow([
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $carRow->engine_id,
                'item_type_id = ?' => self::ITEM_TYPE_ENGINE,
                'value IS NOT NULL'
            ]);

            if ($valueDataRow) {
                return [
                    'empty' => false,
                    'value' => $valueDataRow->value
                ];
            } else {
                return [
                    'empty' => true,
                    'value' => null
                ];
            }

        } else {

            $valueDataRows = $valueDataTable->fetchAll([
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $carRow->engine_id,
                'item_type_id = ?' => self::ITEM_TYPE_ENGINE,
                'value IS NOT NULL'
            ]);

            if (count($valueDataRows)) {
                $value = [];
                foreach ($valueDataRows as $valueDataRow) {
                    $value[] = $valueDataRow->value;
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

    protected function _calcInheritedValue($attribute, $itemTypeId, $itemId)
    {
        $actualValue = [
            'empty' => true,
            'value' => null
        ];

        if ($itemTypeId == 1) {

            $valueDataTable = $this->getValueDataTable($attribute['typeId']);
            $db = $valueDataTable->getAdapter();

            $parentIds = $db->fetchCol(
                $db->select()
                    ->from('car_parent', 'parent_id')
                    ->where('car_id = ?', $itemId)
            );

            if (count($parentIds) > 0) {

                if (!$attribute['isMultiple']) {
                    $idx = 0;
                    $registry = [];
                    $ratios = [];

                    $valueDataRows = $valueDataTable->fetchAll([
                        'attribute_id = ?' => $attribute['id'],
                        'item_id in (?)'   => $parentIds,
                        'item_type_id = ?' => $itemTypeId
                    ]);

                    foreach ($valueDataRows as $valueDataRow) {

                        $value = $valueDataRow->value;

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

                        if (!isset($ratios[$matchRegIdx])) {
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

        } else if ($itemTypeId == 3) {

            $engineRow = $this->getEngineTable()->find($itemId)->current();
            if ($engineRow) {
                $parentEngineRow = $this->getEngineTable()->find($engineRow->parent_id)->current();

                if ($parentEngineRow) {

                    $valueDataTable = $this->getValueDataTable($attribute['typeId']);

                    if (!$attribute['isMultiple']) {

                        $valueDataRow = $valueDataTable->fetchRow([
                            'attribute_id = ?' => $attribute['id'],
                            'item_id = ?'      => $parentEngineRow->id,
                            'item_type_id = ?' => $itemTypeId
                        ]);

                        if ($valueDataRow) {
                            $actualValue = [
                                'empty' => false,
                                'value' => $valueDataRow->value
                            ];
                        }

                    } else {

                        $valueDataRows = $valueDataTable->fetchAll([
                            'attribute_id = ?' => $attribute['id'],
                            'item_id = ?'      => $parentEngineRow->id,
                            'item_type_id = ?' => $itemTypeId,
                        ]);

                        if (count($valueDataRows)) {
                            $a = [];
                            foreach ($valueDataRows as $valueDataRow) {
                                $a[] = $valueDataRow->value;
                            }

                            $actualValue = [
                                'empty' => false,
                                'value' => $a
                            ];
                        }

                    }
                }
            }
        }

        return $actualValue;
    }

    protected function _setActualValue($attribute, $itemTypeId, $itemId, array $actualValue)
    {
        $valueTable = $this->getValueTable();
        $valueDataTable = $this->getValueDataTable($attribute['typeId']);

        $somethingChanges = false;

        if ($actualValue['empty']) {

            // descriptor
            $row = $valueTable->fetchRow([
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $itemId,
                'item_type_id = ?' => $itemTypeId
            ]);
            if ($row) {
                $row->delete();
                $somethingChanges = true;
            }

            // value
            $rows = $valueDataTable->fetchAll([
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $itemId,
                'item_type_id = ?' => $itemTypeId
            ]);
            foreach ($rows as $row) {
                $row->delete();
                $somethingChanges = true;
            }
        } else {

            // descriptor
            $valueRow = $valueTable->fetchRow([
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $itemId,
                'item_type_id = ?' => $itemTypeId
            ]);
            if (!$valueRow) {
                $valueRow = $valueTable->createRow([
                    'attribute_id' => $attribute['id'],
                    'item_id'      => $itemId,
                    'item_type_id' => $itemTypeId,
                    'update_date'  => new Zend_Db_Expr('now()')
                ]);
                $valueRow->save();
                $somethingChanges = true;
            }

            // value
            if ($attribute['isMultiple']) {
                $rows = $valueDataTable->fetchAll([
                    'attribute_id = ?' => $attribute['id'],
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId
                ]);
                foreach ($rows as $row) {
                    $row->delete();
                    $somethingChanges = true;
                }

                foreach ($actualValue['value'] as $ordering => $value) {
                    $rows = $valueDataTable->insert([
                        'attribute_id' => $attribute['id'],
                        'item_id'      => $itemId,
                        'item_type_id' => $itemTypeId,
                        'ordering'     => $ordering,
                        'value'        => $value
                    ]);
                    $somethingChanges = true;
                }

            } else {
                $row = $valueDataTable->fetchRow([
                    'attribute_id = ?' => $attribute['id'],
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId,
                ]);
                if (!$row) {
                    $row = $valueDataTable->createRow([
                        'attribute_id' => $attribute['id'],
                        'item_id'      => $itemId,
                        'item_type_id' => $itemTypeId,
                        'value'        => $actualValue['value']
                    ]);
                    $row->save();
                    $somethingChanges = true;
                } else {
                    if ($actualValue['value'] === null || $row->value === null) {
                        $valueDifferent = $actualValue['value'] !== $row->value;
                    } else {
                        $valueDifferent = $actualValue['value'] != $row->value;
                    }
                    if ($valueDifferent) {
                        $row->value = $actualValue['value'];
                        $row->save();
                        $somethingChanges = true;
                    }
                }

            }

            if ($somethingChanges) {
                $valueRow->update_date = new Zend_Db_Expr('now()');
                $valueRow->save();
            }
        }

        return $somethingChanges;
    }

    public function updateActualValue($attributeId, $itemTypeId, $itemId)
    {
        $attribute = $this->_getAttribute($attributeId);
        return $this->_updateActualValue($attribute, $itemTypeId, $itemId);
    }

    protected function _updateActualValue($attribute, $itemTypeId, $itemId)
    {
        $actualValue = $this->_calcAvgUserValue($attribute, $itemTypeId, $itemId);

        if ($actualValue['empty']) {
            $actualValue = $this->_calcEngineValue($attribute, $itemTypeId, $itemId);
        }

        if ($actualValue['empty']) {
            $actualValue = $this->_calcInheritedValue($attribute, $itemTypeId, $itemId);
        }

        return $this->_setActualValue($attribute, $itemTypeId, $itemId, $actualValue);

    }

    /**
     * @param int $itemTypeId
     * @param int|array $itemId
     * @return boolean|array
     */
    public function hasSpecs($itemTypeId, $itemId)
    {
        $valueTable = $this->getValueTable();
        $db = $valueTable->getAdapter();
        $select = $db->select()
            ->from($valueTable->info('name'), 'item_id')
            ->where('item_type_id = ?', $itemTypeId);
        if (is_array($itemId)) {
            if (count($itemId) <= 0) {
                return false;
            }
            $ids = $db->fetchCol(
                $select
                    ->distinct()
                    ->where('item_id in (?)', $itemId)
            );
            $result = [];
            foreach ($itemId as $id) {
                $result[(int)$id] = false;
            }
            foreach ($ids as $id) {
                $result[(int)$id] = true;
            }
            return $result;
        } else {
            return (bool)$db->fetchOne(
                $select
                    ->where('item_id = ?', (int)$itemId)
                    ->limit(1)
            );
        }
    }

    /**
     * @param array $itemId
     * @return array
     */
    public function twinsGroupsHasSpecs(array $groupIds)
    {
        if (count($groupIds) <= 0) {
            return [];
        }

        $valueTable = $this->getValueTable();
        $db = $valueTable->getAdapter();
        $select = $db->select()
            ->from($valueTable->info('name'), ['twins_groups_cars.twins_group_id', new Zend_Db_Expr('1')])
            ->where('attrs_values.item_type_id = ?', self::ITEM_TYPE_CAR)
            ->join('twins_groups_cars', 'attrs_values.item_id = twins_groups_cars.car_id', null)
            ->where('twins_groups_cars.twins_group_id in (?)', $groupIds);

        return $db->fetchPairs($select);
    }

    /**
     * @param int $itemTypeId
     * @param int $itemId
     * @return int
     */
    public function getSpecsCount($itemTypeId, $itemId)
    {
        $table = $this->getValueTable();
        $db = $table->getAdapter();
        return (int)$db->fetchOne(
            $db->select()
                ->from($table->info('name'), new Zend_Db_Expr('count(1)'))
                ->where('item_id = ?', (int)$itemId)
                ->where('item_type_id = ?', (int)$itemTypeId)
        );
    }

    /**
     * @param int $itemTypeId
     * @param int|array $itemId
     * @return boolean|array
     */


    public function hasChildSpecs($itemTypeId, $itemId)
    {
        if ($itemTypeId == 1) {
            $valueTable = $this->getValueTable();
            $db = $valueTable->getAdapter();
            $select = $db->select()
                ->from($valueTable->info('name'), 'car_parent.parent_id')
                ->where('attrs_values.item_type_id = ?', $itemTypeId)
                ->join('car_parent', 'attrs_values.item_id = car_parent.car_id', null);
            if (is_array($itemId)) {
                if (count($itemId) <= 0) {
                    return [];
                }
                $ids = $db->fetchCol(
                    $select
                        ->distinct()
                        ->where('car_parent.parent_id IN (?)', $itemId)
                );
                $result = [];
                foreach ($itemId as $id) {
                    $result[(int)$id] = false;
                }
                foreach ($ids as $id) {
                    $result[(int)$id] = true;
                }
                return $result;
            } else {
                return (bool)$db->fetchOne(
                    $select
                        ->where('car_parent.parent_id = ?', $itemId)
                );
            }
        }

        return false;

    }


    public function updateActualValues($itemTypeId, $itemId)
    {
        foreach ($this->getAttributes() as $attribute) {
            if ($attribute['typeId']) {
                $this->_updateActualValue($attribute, $itemTypeId, $itemId);
            }
        }
    }

    /**
     * @param int $itemTypeId
     * @param int $itemId
     */
    public function updateInheritedValues($itemTypeId, $itemId)
    {
        foreach ($this->getAttributes() as $attribute) {
            if ($attribute['typeId']) {
                $haveValue = $this->haveOwnAttributeValue($attribute['id'], $itemTypeId, $itemId);
                if (!$haveValue) {
                    $this->_updateActualValue($attribute, $itemTypeId, $itemId);
                }
            }
        }
    }

    public function getContributors($itemTypeId, $itemId)
    {
        if (!$itemId) {
            return [];
        }

        $uvTable = $this->getUserValueTable();
        $db = $uvTable->getAdapter();

        $pairs = $db->fetchPairs(
            $db->select(true)
                ->from($uvTable->info('name'), ['user_id', 'c' => new Zend_Db_Expr('COUNT(1)')])
                ->where('attrs_user_values.item_type_id = ?', (int)$itemTypeId)
                ->where('attrs_user_values.item_id IN (?)', (array)$itemId)
                ->group('attrs_user_values.user_id')
                ->order('c desc')
        );

        return $pairs;
    }

    private function _prepareValue($typeId, $value)
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
     * @param int $zoneId
     * @param int $itemId
     * @param int $userId
     * @throws Exception
     * @return array
     */
    public function getZoneUserValues($zoneId, $itemId, $userId)
    {
        if (!$itemId) {
            throw new Exception("Item_id not set");
        }

        $zone = $this->_getZone($zoneId);
        $itemTypeId = $zone->item_type_id;

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
                if (!isset($requests[$typeId][$isMultiple])) {
                    $requests[$typeId][$isMultiple] = [];
                }
                $requests[$typeId][$isMultiple][] = $attribute['id'];
            }
        }

        $values = [];
        foreach ($requests as $typeId => $multiples) {
            foreach ($multiples as $isMultiple => $ids) {
                $valuesTable = $this->getUserValueDataTable($typeId);
                if (!$valuesTable) {
                    throw new Exception("ValueTable not found");
                }

                $select = $valuesTable->select()
                    ->where('attribute_id in (?)', $ids)
                    ->where('item_id = ?', (int)$itemId)
                    ->where('item_type_id = ?', (int)$itemTypeId)
                    ->where('user_id = ?', (int)$userId);

                if ($isMultiple) {
                    $select->order('ordering');
                }

                foreach ($valuesTable->fetchAll($select) as $row) {
                    $aid = (int)$row->attribute_id;
                    $value = $this->_prepareValue($typeId, $row->value);
                    if ($isMultiple) {
                        if (!isset($values[$aid])) {
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
    public function getZoneUsersValues($zoneId, $itemId)
    {
        if (!$itemId) {
            throw new Exception("Item_id not set");
        }

        $zone = $this->_getZone($zoneId);
        $itemTypeId = $zone->item_type_id;

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
                if (!isset($requests[$typeId][$isMultiple])) {
                    $requests[$typeId][$isMultiple] = [];
                }
                $requests[$typeId][$isMultiple][] = $attribute['id'];
            }
        }

        $values = [];
        foreach ($requests as $typeId => $multiples) {
            foreach ($multiples as $isMultiple => $ids) {
                $valuesTable = $this->getUserValueDataTable($typeId);
                if (!$valuesTable) {
                    throw new Exception("ValueTable not found");
                }

                $select = $valuesTable->select()
                    ->where('attribute_id in (?)', $ids)
                    ->where('item_id = ?', (int)$itemId)
                    ->where('item_type_id = ?', (int)$itemTypeId);

                if ($isMultiple) {
                    $select->order('ordering');
                }

                foreach ($valuesTable->fetchAll($select) as $row) {
                    $aid = (int)$row->attribute_id;
                    $uid = (int)$row->user_id;
                    $value = $this->_prepareValue($typeId, $row->value);
                    if (!isset($values[$aid])) {
                        $values[$aid] = [];
                    }
                    if ($isMultiple) {
                        if (!isset($values[$aid][$uid])) {
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

    public function getUserValue($attributeId, $itemTypeId, $itemId, $userId)
    {
        if (!$itemId) {
            throw new Exception("Item_id not set");
        }

        $attribute = $this->_getAttribute($attributeId);
        if (!$attribute) {
            throw new Exception("attribute not found");
        }

        $valuesTable = $this->getUserValueDataTable($attribute['typeId']);
        if (!$valuesTable) {
            return null;
        }

        $select = $valuesTable->select()
            ->where('attribute_id = ?', (int)$attribute['id'])
            ->where('item_id = ?', (int)$itemId)
            ->where('item_type_id = ?', (int)$itemTypeId)
            ->where('user_id = ?', (int)$userId);

        if ($attribute['isMultiple']) {
            $select->order('ordering');
        }

        $values = [];
        foreach ($valuesTable->fetchAll($select) as $row) {
            $values[] = $this->_prepareValue($attribute['typeId'], $row->value);
        }

        if (count($values) <= 0) {
            return null;
        }

        return $attribute['isMultiple'] ? $values : $values[0];
    }

    public function getUserValueText($attributeId, $itemTypeId, $itemId, $userId, $language)
    {
        if (!$itemId) {
            throw new Exception("Item_id not set");
        }

        $attribute = $this->_getAttribute($attributeId);
        if (!$attribute) {
            throw new Exception("attribute not found");
        }

        $valuesTable = $this->getUserValueDataTable($attribute['typeId']);
        if (!$valuesTable) {
            return null;
        }

        $select = $valuesTable->select()
            ->where('attribute_id = ?', (int)$attribute['id'])
            ->where('item_id = ?', (int)$itemId)
            ->where('item_type_id = ?', (int)$itemTypeId)
            ->where('user_id = ?', (int)$userId);

        if ($attribute['isMultiple']) {
            $select->order('ordering');
        }

        $values = [];
        foreach ($valuesTable->fetchAll($select) as $row) {
            $values[] = $this->valueToText($attribute, $row->value, $language);
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

    public function getActualValueText($attributeId, $itemTypeId, $itemId, $language)
    {
        if (!$itemId) {
            throw new Exception("Item_id not set");
        }

        $attribute = $this->_getAttribute($attributeId);
        if (!$attribute) {
            throw new Exception("attribute not found");
        }

        $value = $this->getActualValue($attribute, $itemId, $itemTypeId);

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
     * @param int $itemTypeId
     * @return array
     */
    private function _getItemsActualValues($itemIds, $itemTypeId)
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
            $valuesTable = $this->getValueDataTable($typeId);
            if (!$valuesTable) {
                throw new Exception("ValueTable not found");
            }

            $select = $valuesTable->select()
                ->where('item_id in (?)', $itemIds)
                ->where('item_type_id = ?', (int)$itemTypeId);

            if ($isMultiple) {
                $select->order('ordering');
            }

            foreach ($valuesTable->fetchAll($select) as $row) {
                $aid = (int)$row->attribute_id;
                $id = (int)$row->item_id;
                $value = $this->_prepareValue($typeId, $row->value);
                if (!isset($values[$id])) {
                    $values[$id] = [];
                }

                $attribute = $this->_getAttribute($aid);

                if ($attribute['isMultiple']) {
                    if (!isset($values[$id][$aid])) {
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
     * @param unknown $zoneId
     * @param array $itemIds
     * @param int $itemTypeId
     * @return array
     */
    private function _getZoneItemsActualValues($zoneId, array $itemIds)
    {
        if (count($itemIds) <= 0) {
            return [];
        }

        $zone = $this->_getZone($zoneId);
        $itemTypeId = $zone->item_type_id;

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
                if (!isset($requests[$typeId][$isMultiple])) {
                    $requests[$typeId][$isMultiple] = [];
                }
                $requests[$typeId][$isMultiple][] = $attribute['id'];
            }
        }

        $values = [];
        foreach ($requests as $typeId => $multiples) {
            foreach ($multiples as $isMultiple => $ids) {
                $valuesTable = $this->getValueDataTable($typeId);
                if (!$valuesTable) {
                    throw new Exception("ValueTable not found");
                }

                $select = $valuesTable->select()
                    ->where('attribute_id in (?)', $ids)
                    ->where('item_id in (?)', $itemIds)
                    ->where('item_type_id = ?', (int)$itemTypeId);

                if ($isMultiple) {
                    $select->order('ordering');
                }

                foreach ($valuesTable->fetchAll($select) as $row) {
                    $aid = (int)$row->attribute_id;
                    $id = (int)$row->item_id;
                    $value = $this->_prepareValue($typeId, $row->value);
                    if (!isset($values[$id])) {
                        $values[$id] = [];
                    }
                    if ($isMultiple) {
                        if (!isset($values[$id][$aid])) {
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
     * @param int $zoneId
     * @param int $itemId
     * @param int $userId
     * @throws Exception
     * @return array
     */
    public function getZoneActualValues($zoneId, $itemId)
    {
        if (!$itemId) {
            throw new Exception("Item_id not set");
        }

        $zone = $this->_getZone($zoneId);
        $itemTypeId = $zone->item_type_id;

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
                if (!isset($requests[$typeId][$isMultiple])) {
                    $requests[$typeId][$isMultiple] = [];
                }
                $requests[$typeId][$isMultiple][] = $attribute['id'];
            }
        }

        $values = [];
        foreach ($requests as $typeId => $multiples) {
            foreach ($multiples as $isMultiple => $ids) {
                $valuesTable = $this->getValueDataTable($typeId);
                if (!$valuesTable) {
                    throw new Exception("ValueTable not found");
                }

                $select = $valuesTable->select()
                    ->where('attribute_id in (?)', $ids)
                    ->where('item_id = ?', (int)$itemId)
                    ->where('item_type_id = ?', (int)$itemTypeId);

                if ($isMultiple) {
                    $select->order('ordering');
                }

                foreach ($valuesTable->fetchAll($select) as $row) {
                    $aid = (int)$row->attribute_id;
                    $value = $this->_prepareValue($typeId, $row->value);
                    if ($isMultiple) {
                        if (!isset($values[$aid])) {
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
    public function getType($typeId)
    {
        if ($this->types === null) {
            $this->types = [];
            foreach ($this->getTypeTable()->fetchAll() as $row) {
                $this->types[(int)$row->id] = [
                    'id'        => (int)$row->id,
                    'name'      => $row->name,
                    'element'   => $row->element,
                    'maxlength' => $row->maxlength,
                    'size'      => $row->size
                ];
            }
        }

        if (!isset($this->types[$typeId])) {
            throw new Exception("Type `$typeId` not found");
        }

        return $this->types[$typeId];
    }

    public function refreshConflictFlag($attributeId, $itemTypeId, $itemId)
    {
        if (!$attributeId) {
            throw new Exception("attributeId not provided");
        }

        if (!$itemTypeId) {
            throw new Exception("itemTypeId not provided");
        }

        if (!$itemId) {
            throw new Exception("itemId not provided");
        }

        $attribute = $this->_getAttribute($attributeId);
        if (!$attribute) {
            throw new Exception("Attribute not found");
        }

        $userValueTable = $this->getUserValueTable();
        $userValueRows = $userValueTable->fetchAll([
            'attribute_id = ?' => $attribute['id'],
            'item_id = ?'      => $itemId,
            'item_type_id = ?' => $itemTypeId
        ]);

        $userValues = [];
        $uniqueValues = [];
        foreach ($userValueRows as $userValueRow) {
            $v = $this->getUserValue($attribute['id'], $itemTypeId, $itemId, $userValueRow['user_id']);
            $serializedValue = serialize($v);
            $uniqueValues[] = $serializedValue;
            $userValues[$userValueRow['user_id']] = [
                'value' => $serializedValue,
                'date'  => $userValueRow['update_date']
            ];
        }

        $uniqueValues = array_unique($uniqueValues);
        $hasConflict = count($uniqueValues) > 1;

        $valueRow = $this->getValueTable()->fetchRow([
            'attribute_id = ?' => $attribute['id'],
            'item_id = ?'      => $itemId,
            'item_type_id = ?' => $itemTypeId
        ]);

        if (!$valueRow) {
            return;
            //throw new Exception("Value row not found");
        }

        $valueRow->conflict = $hasConflict ? 1 : 0;
        $valueRow->save();

        $affectedUserIds = [];

        if ($hasConflict) {
            $actualValue = serialize($this->getActualValue($attributeId, $itemId, $itemTypeId));

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

                $affectedRows = $userValueTable->update([
                    'conflict' => $conflict,
                    'weight'   => $weight
                ], [
                    'user_id = ?'      => $userId,
                    'attribute_id = ?' => $attributeId,
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId
                ]);

                if ($affectedRows) {
                    $affectedUserIds[] = $userId;
                }
            }
        } else {
            $affectedRows = $userValueTable->update([
                'conflict' => 0,
                'weight'   => self::WEIGHT_NONE
            ], [
                'attribute_id = ?' => $attributeId,
                'item_id = ?'      => $itemId,
                'item_type_id = ?' => $itemTypeId
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
            $userValueTable = $this->getUserValueTable();
            $db = $userValueTable->getAdapter();

            $pSelect = $db->select()
                ->from($userValueTable->info('name'), 'sum(weight)')
                ->where('user_id = users.id')
                ->where('weight > 0')
                ->assemble();

            $nSelect = $db->select()
                ->from($userValueTable->info('name'), 'abs(sum(weight))')
                ->where('user_id = users.id')
                ->where('weight < 0')
                ->assemble();

            $expr = new Zend_Db_Expr(
                '1.5 * ((1 + IFNULL((' . $pSelect . '), 0)) / (1 + IFNULL((' . $nSelect . '), 0)))'
            );

            //print $expr . PHP_EOL;

            $db->update('users', [
                'specs_weight' => $expr,
            ], [
                'id IN (?)' => $userId
            ]);
        }

    }

    public function refreshConflictFlags()
    {
        $valueTable = $this->getValueTable();
        $select = $valueTable->select(true)
            ->distinct()
            ->join(
                'attrs_user_values',
                'attrs_values.attribute_id = attrs_user_values.attribute_id ' .
                    'and attrs_values.item_id = attrs_user_values.item_id ' .
                    'and attrs_values.item_type_id = attrs_user_values.item_type_id',
                null
            )
            ->where('attrs_user_values.conflict');

        foreach ($valueTable->fetchAll($select) as $valueRow) {
            print $valueRow['attribute_id'] . '#' . $valueRow['item_type_id'] . '#' . $valueRow['item_id'] . PHP_EOL;
            $this->refreshConflictFlag($valueRow['attribute_id'], $valueRow['item_type_id'], $valueRow['item_id']);
        }
    }

    public function refreshItemConflictFlags($typeId, $itemId)
    {
        $valueTable = $this->getUserValueTable();
        $select = $valueTable->select(true)
            ->where('attrs_user_values.item_id = ?', (int)$itemId)
            ->where('attrs_user_values.item_type_id = ?', (int)$typeId);

        foreach ($valueTable->fetchAll($select) as $valueRow) {
            //print $valueRow['attribute_id'] . '#' . $valueRow['item_type_id'] . '#' . $valueRow['item_id'] . PHP_EOL;
            $this->refreshConflictFlag($valueRow['attribute_id'], $valueRow['item_type_id'], $valueRow['item_id']);
        }
    }

    public function getConflicts($userId, $filter, $page, $perPage, $language)
    {
        $userId = (int)$userId;

        $valueTable = $this->getValueTable();
        $select = $valueTable->select(true)
            ->join(
                'attrs_user_values',
                'attrs_values.attribute_id = attrs_user_values.attribute_id ' .
                    'and attrs_values.item_id = attrs_user_values.item_id ' .
                    'and attrs_values.item_type_id = attrs_user_values.item_type_id',
                null
            )
            ->where('attrs_user_values.user_id = ?', $userId)
            ->order('attrs_values.update_date desc');

        if ($filter == 'minus-weight') {
            $select->where('attrs_user_values.weight < 0');
        } elseif ($filter == 0) {
            $select->where('attrs_values.conflict');
        } elseif ($filter > 0) {
            $select->where('attrs_user_values.conflict > 0');
        } elseif ($filter < 0) {
            $select->where('attrs_user_values.conflict < 0');
        }

        $userValueTable = $this->getUserValueTable();

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage($perPage)
            ->setCurrentPageNumber($page);

        $conflicts = [];
        foreach ($paginator->getCurrentItems() as $valueRow) {

            // other users values
            $userValueRows = $userValueTable->fetchAll([
                'attribute_id = ?' => $valueRow['attribute_id'],
                'item_id = ?'      => $valueRow['item_id'],
                'item_type_id = ?' => $valueRow['item_type_id'],
                'user_id <> ?'     => $userId
            ]);

            $values = [];
            foreach ($userValueRows as $userValueRow) {
                $values[] = [
                    'value'  => $this->getUserValueText(
                        $userValueRow['attribute_id'],
                        $userValueRow['item_type_id'],
                        $userValueRow['item_id'],
                        $userValueRow['user_id'],
                        $language
                    ),
                    'userId' => $userValueRow['user_id']
                ];
            }

            // my value
            $userValueRow = $userValueTable->fetchRow([
                'attribute_id = ?' => $valueRow['attribute_id'],
                'item_id = ?'      => $valueRow['item_id'],
                'item_type_id = ?' => $valueRow['item_type_id'],
                'user_id = ?'      => $userId
            ]);
            $value = null;
            if ($userValueRow) {
                $value = $this->getUserValueText(
                    $userValueRow['attribute_id'],
                    $userValueRow['item_type_id'],
                    $userValueRow['item_id'],
                    $userValueRow['user_id'],
                    $language
                );
            }

            $attribute = $this->_getAttribute($valueRow['attribute_id']);

            $unit = null;
            if ($attribute['unitId']) {
                $unit = $this->getUnit($attribute['unitId']);
            }

            $attributeName = [];
            $cAttr = $attribute;
            do {
                $attributeName[] = $this->translator->translate($cAttr['name']);
                $cAttr = $this->_getAttribute($cAttr['parentId']);
            } while ($cAttr);

            $conflicts[] = [
                'itemId'     => $valueRow['item_id'],
                'itemTypeId' => $valueRow['item_type_id'],
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
        $userValueTable = $this->getUserValueTable();
        $db = $userValueTable->getAdapter();

        $userIds = $db->fetchCol(
            $db->select()
                ->distinct()
                ->from($userValueTable->info('name'), ['user_id'])
        );

        $this->refreshUserConflicts($userIds);
    }

    public function refreshUsersConflictsStat()
    {
        $userValueTable = $this->getUserValueTable();
        $db = $userValueTable->getAdapter();

        $pSelect = $db->select()
            ->from($userValueTable->info('name'), 'sum(weight)')
            ->where('user_id = users.id')
            ->where('weight > 0')
            ->assemble();

        $nSelect = $db->select()
            ->from($userValueTable->info('name'), 'abs(sum(weight))')
            ->where('user_id = users.id')
            ->where('weight < 0')
            ->assemble();

        $expr = new Zend_Db_Expr(
            '1.5 * ((1 + IFNULL((' . $pSelect . '), 0)) / (1 + IFNULL((' . $nSelect . '), 0)))'
        );

        //print $expr . PHP_EOL;

        $db->update('users', [
            'specs_weight' => $expr,
        ]);
    }

    /*public static function valueWeight($positives, $negatives) {
        if ($negatives <= 1) {
            $negatives = 1;
        }
        if ($positives <= 1) {
            $positives = 1;
        }
        return 1 * $positives / ($negatives / 1.5);
    }*/

    public function getUserValueWeight($userId)
    {
        if (!array_key_exists($userId, $this->valueWeights)) {
            $userRow = $this->getUserTable()->find($userId)->current();
            if ($userRow) {
                $this->valueWeights[$userId] = $userRow->specs_weight;
            } else {
                $this->valueWeights[$userId] = 1;
            }
        }

        return $this->valueWeights[$userId];
    }
}