<?php

use Application\Paginator\Adapter\Zend1DbTableSelect;

class Application_Service_Specifications
{
    const ITEM_TYPE_CAR = 1;
    const ITEM_TYPE_ENGINE = 3;

    const ENGINE_ZONE_ID = 5;

    const NULL_VALUE_STR = '-';

    const WEIGHT_NONE          =  0,
          WEIGHT_FIRST_ACTUAL  =  1,
          WEIGHT_SECOND_ACTUAL =  0.1,
          WEIGHT_WRONG         = -1;


    protected $_zones = null;

    /**
     * @var Attrs_Attributes
     */
    protected $_attributeTable = null;

    /**
     * @var Attrs_List_Options
     */
    protected $_listOptionsTable = null;

    /**
     * @var array
     */
    private $_listOptions = [];

    private $_listOptionsChilds = [];

    /**
     * @var Attrs_Units
     */
    protected $_unitTable = null;

    protected $_valueTables = [];

    protected $_userValueDataTables = [];

    protected $_units = null;

    /**
     * @var Attrs_User_Values
     */
    protected $_userValueTable = null;

    /**
     * @var Attrs_User_Values
     */
    protected $_userValuesTable = null;

    protected $_attributeRows = null;

    protected $_attributes = null;

    protected $_childs = null;

    protected $_attributeChilds = null;

    protected $_zoneAttrs = [];

    /**
     * @var Cars
     */
    protected $_carTable = null;

    /**
     * @var Car_Parent
     */
    protected $_carParentTable = null;

    /**
     * @var array
     */
    protected $_carChildsCache = [];

    /**
     * @var array
     */
    protected $_engineChildsCache = [];

    /**
     * @var Attrs_Values
     */
    protected $_valueTable = null;

    /**
     * @var array
     */
    protected $_actualValueCache = [];

    /**
     * @var array
     */
    protected $_engineAttributes = null;

    /**
     * @var Engines
     */
    protected $_engineTable = null;

    /**
     * @var Attrs_Types
     */
    protected $_typeTable;

    /**
     * @var array
     */
    protected $_types = null;

    /**
     * @var Users
     */
    protected $_userTable;

    /**
     * @var array
     */
    protected $_users = [];

    protected $_valueWeights = [];

    /**
     * @return Users
     */
    private function _getUserTable()
    {
        return $this->_userTable
            ? $this->_userTable
            : $this->_userTable = new Users();
    }

    /**
     * @param int $userId
     * @return array
     */
    private function _getUser($userId)
    {
        if (!isset($this->_users[$userId])) {
            $userRow = $this->_getUserTable()->find($userId)->current();
            $this->_users[$userId] = $userRow;
        }

        return $this->_users[$userId];
    }

    /**
     * @return Attrs_Types
     */
    private function _getTypeTable()
    {
        return $this->_typeTable
            ? $this->_typeTable
            : $this->_typeTable = new Attrs_Types();
    }

    /**
     * @return Attrs_Values
     */
    protected function _getValueTable()
    {
        return $this->_valueTable
            ? $this->_valueTable
            : $this->_valueTable = new Attrs_Values();
    }

    /**
     * @return Car_Parent
     */
    protected function _getCarParentTable()
    {
        return $this->_carParentTable
            ? $this->_carParentTable
            : $this->_carParentTable = new Car_Parent();
    }

    /**
     * @return Engines
     */
    protected function _getEngineTable()
    {
        return $this->_engineTable
            ? $this->_engineTable
            : $this->_engineTable = new Engines();
    }

    /**
     * @return Cars
     */
    protected function _getCarTable()
    {
        return $this->_carTable
            ? $this->_carTable
            : $this->_carTable = new Cars();
    }

    protected function _getAttributeTable()
    {
        return $this->_attributeTable
            ? $this->_attributeTable
            : $this->_attributeTable = new Attrs_Attributes();
    }

    protected function _getUserValueTable()
    {
        return $this->_userValueTable
            ? $this->_userValueTable
            : $this->_userValueTable = new Attrs_User_Values();
    }

    protected function _getListOptionsTable()
    {
        return $this->_listOptionsTable
            ? $this->_listOptionsTable
            : $this->_listOptionsTable = new Attrs_List_Options();
    }

    protected function _getUnitTable()
    {
        return $this->_unitTable
            ? $this->_unitTable
            : $this->_unitTable = new Attrs_Units();
    }

    protected function _getZone($id)
    {
        if ($this->_zones === null) {
            $zoneTable = new Attrs_Zones();
            $this->_zones = [];
            foreach ($zoneTable->fetchAll() as $zone) {
                $this->_zones[$zone->id] = $zone;
            }
        }

        if (!isset($this->_zones[$id])) {
            throw new Exception("Zone `$id` not found");
        }

        return $this->_zones[$id];
    }

    public function getUnit($id)
    {
        if ($this->_units === null) {
            $units = [];
            foreach ($this->_getUnitTable()->fetchAll() as $unit) {
                $units[$unit->id] = array(
                    'id'   => (int)$unit->id,
                    'name' => $unit->name,
                    'abbr' => $unit->abbr
                );
            }

            $this->_units = $units;
        }

        $id = (int)$id;

        return isset($this->_units[$id]) ? $this->_units[$id] : null;
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

    private function _walkTree($zoneId, Callable $callback)
    {
        $this->_loadAttributes();
        $this->loadZone($zoneId);

        return $this->_walkTreeStep($zoneId, 0, $callback);
    }

    private function _walkTreeStep($zoneId, $parent, Callable $callback)
    {
        $attributes = $this->getAttributes(array(
            'parent' => (int)$parent,
            'zone'   => $zoneId
        ));

        $result = [];

        foreach ($attributes as $attribute) {
            $key = 'attr_' . $attribute['id'];
            $haveChilds = isset($this->_childs[$attribute['id']]);
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
        $ids = array_diff($attributeIds, array_keys($this->_listOptions));

        if (count($ids)) {
            $rows = $this->_getListOptionsTable()->fetchAll(array(
                'attribute_id IN (?)' => $ids
            ), 'position');

            foreach ($rows as $row) {
                $aid = (int)$row->attribute_id;
                $id = (int)$row->id;
                $pid = (int)$row->parent_id;
                if (!isset($this->_listOptions[$aid])) {
                    $this->_listOptions[$aid] = [];
                }
                $this->_listOptions[$aid][$id] = $row->name;
                if (!isset($this->_listOptionsChilds[$aid][$pid])) {
                    $this->_listOptionsChilds[$aid][$pid] = array($id);
                } else {
                    $this->_listOptionsChilds[$aid][$pid][] = $id;
                }
            }
        }
    }

    private function _getListsOptions(array $attributeIds)
    {
        $this->_loadListOptions($attributeIds);

        $result = [];
        foreach ($attributeIds as $aid) {
            if (isset($this->_listOptions[$aid])) {
                $result[$aid] = $this->_getListOptions($aid, 0);
            }
        }

        return $result;
    }

    private function _getListOptions($aid, $parentId)
    {
        $parentId = (int)$parentId;

        $result = [];
        if (isset($this->_listOptionsChilds[$aid][$parentId])) {
            foreach ($this->_listOptionsChilds[$aid][$parentId] as $childId) {
                $result[$childId] = $this->_listOptions[$aid][$childId];
                $childOptions = $this->_getListOptions($aid, $childId);
                foreach ($childOptions as &$value) {
                    $value = '…' . $value;
                }
                unset($value); // prevent future bugs
                $result = array_replace($result, $childOptions);
            }
        }
        return $result;
    }

    private function _getListOptionsText($attributeId, $id)
    {
        $this->_loadListOptions(array($attributeId));

        if (!isset($this->_listOptions[$attributeId][$id])) {
            throw new Exception("list option `$id` not found");
        }

        return $this->_listOptions[$attributeId][$id];
    }

    /**
     * @param int $itemId
     * @param int $zoneId
     * @param Users_Row $user
     * @param array $options
     * @return Application_Form_Attrs_Zone_Attributes
     */
    private function getForm($itemId, $zoneId, Users_Row $user, array $options)
    {
        $multioptions = $this->_getListsOptions($this->loadZone($zoneId));

        $zoneUserValues = $this->getZoneUsersValues($zoneId, $itemId);

        $zone = $this->_getZone($zoneId);
        $itemTypeId = $zone->item_type_id;

        $userValueTable = $this->_getUserValueTable();

        // fetch values dates
        $dates = [];
        if (count($zoneUserValues)) {
            $valueDescRows = $userValueTable->fetchAll(array(
                'attribute_id IN (?)' => array_keys($zoneUserValues),
                'item_id = ?'         => $itemId,
                'item_type_id = ?'    => $itemTypeId,
            ));
            foreach ($valueDescRows as $valueDescRow) {
                $dates[$valueDescRow->attribute_id][$valueDescRow->user_id] = $valueDescRow->getDate('update_date');
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
                    'value' => $this->_valueToText($attribute, $value),
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

            $actualValues[$attributeId] = $this->_valueToText($attribute, $value);
        }

        $options = array_replace($options, [
            'service'      => $this,
            'zone'         => $this->_getZone($zoneId),
            'itemId'       => $itemId,
            'allValues'    => $allValues,
            'actualValues' => $actualValues,
            'multioptions' => $multioptions,
            'editableAttributes' => array_keys($currentUserValues)
        ]);

        //$currentUserValues = $this->getZoneUserValues($zoneId, $itemId, $user->id);

        $form = new Application_Form_Attrs_Zone_Attributes($options);
        $formValues = $this->_walkTree($zoneId, function($attribute) use ($currentUserValues) {
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
        $form->populate($formValues);

        return $form;
    }

    /**
     * @param Cars_Row $car
     * @param array $options
     * @return Application_Form_Attrs_Zone_Attributes
     */
    public function getCarForm(Cars_Row $car, Users_Row $user, array $options)
    {
        $zoneId = $this->_zoneIdByCarTypeId($car->car_type_id);
        return $this->getForm($car->id, $zoneId, $user, $options);
    }

    /**
     * @param Engines_Row $engine
     * @param Users_Row $user
     * @param array $options
     * @return Application_Form_Attrs_Zone_Attributes
     */
    public function getEngineForm(Engines_Row $engine, Users_Row $user, array $options)
    {
        $zoneId = 5;
        return $this->getForm($engine->id, $zoneId, $user, $options);
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
        if (!isset($this->_zoneAttrs[$id])) {
            $db = $this->_getAttributeTable()->getAdapter();
            $this->_zoneAttrs[$id] = $db->fetchCol(
                $db->select()
                    ->from('attrs_zone_attributes', 'attribute_id')
                    ->where('zone_id = ?', $id)
                    ->order('position')
            );
        }

        return $this->_zoneAttrs[$id];
    }

    /**
     * @return Application_Service_Specifications
     */
    protected function _loadAttributes()
    {
        if ($this->_attributes === null) {
            $array = [];
            $childs = [];
            foreach ($this->_getAttributeTable()->fetchAll(null, 'position') as $row) {
                $id = (int)$row->id;
                $pid = (int)$row->parent_id;
                $array[$id] = array(
                    'id'          => $id,
                    'name'        => $row->name,
                    'description' => $row->description,
                    'typeId'      => (int)$row->type_id,
                    'unitId'      => (int)$row->unit_id,
                    'isMultiple'  => $row->isMultiple(),
                    'precision'   => $row->precision,
                    'parentId'    => $pid ? $pid : null
                );
                if (!isset($childs[$pid])) {
                    $childs[$pid] = array($id);
                } else {
                    $childs[$pid][] = $id;
                }
            }

            $this->_attributes = $array;
            $this->_childs = $childs;
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
        return isset($this->_attributes[$id]) ? $this->_attributes[$id] : null;
    }

    public function setUserValue($uid, $attributeId, $itemTypeId, $itemId, $value)
    {
        $attribute = $this->_getAttribute($attributeId);
        $somethingChanged = false;

        $userValueTable = $this->_getUserValueTable();
        $userValueDataTable = $this->getUserValueDataTable($attribute['typeId']);

        if ($attribute['isMultiple']) {

            // удаляем дескрипторы значений
            $userValues = $userValueTable->fetchAll(array(
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $itemId,
                'item_type_id = ?' => $itemTypeId,
                'user_id = ?'      => $uid,
            ));
            foreach ($userValues as $userValue) {
                $userValue->delete();
            }
            // удаляем значение
            $userValueDataRows = $userValueDataTable->fetchAll(array(
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $itemId,
                'item_type_id = ?' => $itemTypeId,
                'user_id = ?'      => $uid
            ));
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

                    // вставляем новые дексрипторы и значения
                    $userValueTable->insert(array(
                        'attribute_id' => $attribute['id'],
                        'item_id'      => $itemId,
                        'item_type_id' => $itemTypeId,
                        'user_id'      => $uid,
                        'add_date'     => new Zend_Db_Expr('NOW()'),
                        'update_date'  => new Zend_Db_Expr('NOW()'),
                    ));
                    $ordering = 1;

                    if ($valueNot) {
                        $value = array(null);
                    }

                    foreach ($value as $oneValue) {
                        $userValueDataTable->insert(array(
                            'attribute_id' => $attribute['id'],
                            'item_id'      => $itemId,
                            'item_type_id' => $itemTypeId,
                            'user_id'      => $uid,
                            'ordering'     => $ordering,
                            'value'        => $oneValue
                        ));

                        $ordering++;
                    }
                }

            }

            $somethingChanged = $this->_updateActualValue($attribute, $itemTypeId, $itemId);

        } else {

            if (strlen($value) > 0) {
                // вставлям/обновляем дескриптор значения
                $userValue = $userValueTable->fetchRow(array(
                    'attribute_id = ?' => $attribute['id'],
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId,
                    'user_id = ?'      => $uid
                ));

                // вставляем/обновляем значение
                $userValueData = $userValueDataTable->fetchRow(array(
                    'attribute_id = ?' => $attribute['id'],
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId,
                    'user_id = ?'      => $uid
                ));

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
                        $userValue = $userValueTable->createRow(array(
                            'attribute_id' => $attribute['id'],
                            'item_id'      => $itemId,
                            'item_type_id' => $itemTypeId,
                            'user_id'      => $uid,
                            'add_date'     => new Zend_Db_Expr('NOW()')
                        ));
                    }

                    $userValue->setFromArray(array(
                        'update_date' => new Zend_Db_Expr('NOW()')
                    ));
                    $userValue->save();

                    if (!$userValueData) {
                        $userValueData = $userValueDataTable->fetchNew();
                        $userValueData->setFromArray(array(
                            'attribute_id' => $attribute['id'],
                            'item_id'      => $itemId,
                            'item_type_id' => $itemTypeId,
                            'user_id'      => $uid
                        ));
                    }

                    $userValueData->value = $value;
                    $userValueData->save();

                    $somethingChanged = $this->_updateActualValue($attribute, $itemTypeId, $itemId);
                }

            } else {

                $needUpdate = false;
                // удаляем дескриптор значения
                $userValue = $userValueTable->fetchRow(array(
                    'attribute_id = ?' => $attribute['id'],
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId,
                    'user_id = ?'      => $uid,
                ));
                if ($userValue) {
                    $userValue->delete();
                    $needUpdate = true;
                }
                // удаляем значение
                $userValueData = $userValueDataTable->fetchRow(array(
                    'attribute_id = ?' => $attribute['id'],
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId,
                    'user_id = ?'      => $uid,
                ));
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
     * @param Application_Form_Attrs_Zone_Attributes $form
     * @param Users_Row $user
     */
    public function saveAttrsZoneAttributes(Application_Form_Attrs_Zone_Attributes $form, Users_Row $user)
    {
        $zone = $form->getZone();

        $attributes = $this->getAttributes(array(
            'zone'   => $zone->id,
            'parent' => 0
        ));

        $values = $this->collectFormData($zone->id, $attributes, $form->getValues());

        foreach ($values as $attributeId => $value) {
            $this->setUserValue(
                $user->id,
                $attributeId,
                $zone->item_type_id,
                $form->getItemId(),
                $value
            );
        }

    }

    protected function _getEngineAttributeIds()
    {
        if (!$this->_engineAttributes) {
            $table = $this->_getAttributeTable();
            $db = $table->getAdapter();

            $this->_engineAttributes = $db->fetchCol(
                    $db->select()
                    ->from($table->info('name'), 'id')
                    ->join('attrs_zone_attributes', 'attrs_attributes.id = attrs_zone_attributes.attribute_id', null)
                    ->where('attrs_zone_attributes.zone_id = ?', self::ENGINE_ZONE_ID)
            );
        }

        return $this->_engineAttributes;
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

        $carRows = $this->_getCarTable()->fetchAll(array(
            'engine_id = ?' => $itemId
        ));

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
        if (!isset($this->_carChildsCache[$parentId])) {
            $carParentTable = $this->_getCarParentTable();
            $db = $carParentTable->getAdapter();
            $this->_carChildsCache[$parentId] = $db->fetchCol(
                $db->select()
                    ->from($carParentTable->info('name'), 'car_id')
                    ->where('parent_id = ?', $parentId)
            );
        }

        return $this->_carChildsCache[$parentId];
    }

    /**
     * @param array $attribute
     * @param int $itemTypeId
     * @param int $itemId
     */

    protected function _getChildEngineIds($parentId)
    {
        if (!isset($this->_engineChildsCache[$parentId])) {
            $engineTable = $this->_getEngineTable();
            $db = $engineTable->getAdapter();
            $this->_engineChildsCache[$parentId] = $db->fetchCol(
                $db->select()
                    ->from($engineTable->info('name'), 'id')
                    ->where('parent_id = ?', $parentId)
            );
        }

        return $this->_engineChildsCache[$parentId];
    }

    protected function _haveOwnAttributeValue($attributeId, $itemTypeId, $itemId)
    {
        return (bool)$this->_getUserValueTable()->fetchRow(array(
            'attribute_id = ?' => (int)$attributeId,
            'item_type_id = ?' => (int)$itemTypeId,
            'item_id = ?'      => (int)$itemId
        ));
    }

    protected function _propagateInheritance($attribute, $itemTypeId, $itemId)
    {
        if ($itemTypeId == 1) {

            $childIds = $this->_getChildCarIds($itemId);

            foreach ($childIds as $childId) {
                // update only if row use inheritance
                $haveValue = $this->_haveOwnAttributeValue($attribute['id'], $itemTypeId, $childId);

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
                $haveValue = $this->_haveOwnAttributeValue($attribute['id'], $itemTypeId, $childId);

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
                ->where('pictures.status in (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
                ->order('pictures.id desc')
                ->limit(1)
        );
    }

    protected function _specPicture($car, $perspectives)
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
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                ->where('car_parent_cache.parent_id = ?', $car->id)
                ->where('pictures.status in (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
                ->order($order)
                ->limit(1)
        );
    }

    public function getAttributes(array $options = array())
    {
        $defaults = array(
            'zone'      => null,
            'parent'    => null,
            'recursive' => false
        );
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
                if (isset($this->_childs[$parent])) {
                    $ids = array_intersect($this->_zoneAttrs[$zone], $this->_childs[$parent]);
                }
            } else {
                if (isset($this->_childs[$parent])) {
                    $ids = $this->_childs[$parent];
                }
            }
            foreach ($ids as $id) {
                $attributes[] = $this->_attributes[$id];
            }
        } else {
            if ($zone) {
                $attributes = [];
                if ($parent !== null) {
                    $ids = [];
                    if (isset($this->_childs[$parent])) {
                        $ids = array_intersect($this->_zoneAttrs[$zone], $this->_childs[$parent]);
                    }
                } else {
                    $ids = $this->_zoneAttrs[$zone];
                }
                foreach ($ids as $id) {
                    $attributes[] = $this->_attributes[$id];
                }
            } else {
                $attributes = $this->_attributes;
            }
        }

        if ($recursive) {
            foreach ($attributes as &$attr) {
                $attr['childs'] = $this->getAttributes(array(
                    'zone'      => $zone,
                    'parent'    => $attr['id'],
                    'recursive' => $recursive
                ));
            }
        }

        return $attributes;
    }

    public function getActualValueRangeText($attributeId, array $itemId, $itemTypeId)
    {
        $attribute = $this->_getAttribute($attributeId);

        $range = $this->getActualValueRange($attributeId, $itemId, $itemTypeId);
        if ($range['min'] !== null) {
            $range['min'] = $this->_valueToText($attribute, $range['min']);
        }
        if ($range['max'] !== null) {
            $range['max'] = $this->_valueToText($attribute, $range['max']);
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

        $numericTypes = array(2, 3);

        if (!in_array($attribute['typeId'], $numericTypes)) {
            throw new Exception("Range only for numeric types");
        }


        //if (!isset($this->_actualValueCache[]))

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

        return array(
            'min' => $min,
            'max' => $max
        );
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

        //if (!isset($this->_actualValueCache[]))

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

        $valueTable = $this->_getUserValueTable();
        $row = $valueTable->fetchRow(array(
            'attribute_id = ?' => (int)$attribute['id'],
            'item_id = ?'      => (int)$itemId,
            'item_type_id = ?' => (int)$itemTypeId,
            'user_id = ?'      => (int)$userId
        ));

        $valueDataTable = $this->getUserValueDataTable($attribute['typeId']);
        if (!$valueDataTable) {
            throw new Exception("Failed to allocate data table");
        }

        $dataRows = $valueDataTable->fetchAll(array(
            'attribute_id = ?' => (int)$attribute['id'],
            'item_id = ?'      => (int)$itemId,
            'item_type_id = ?' => (int)$itemTypeId,
            'user_id = ?'      => (int)$userId
        ));

        foreach ($dataRows as $dataRow) {
            $dataRow->delete();
        }

        $row->delete();

        $this->updateActualValue($attribute['id'], $itemTypeId, $itemId);
    }

    protected function _loadValues($attributes, $itemId, $itemTypeId)
    {
        $values = [];
        foreach ($attributes as $attribute) {
            $value = $this->getActualValue($attribute, $itemId, $itemTypeId);
            $valueText = $this->_valueToText($attribute, $value);
            $values[$attribute['id']] = $valueText;

            /*if ($valueText === null) {
                // load child values
            }*/

            foreach ($this->_loadValues($attribute['childs'], $itemId, $itemTypeId) as $id => $value) {
                $values[$id] = $value;
            }
        }
        return $values;
    }

    public function specifications($cars, array $options)
    {
        $options = array_merge(array(
            'contextCarId' => null,
            'language'   => 'en'
        ), $options);

        $language = $options['language'];
        $contextCarId = (int)$options['contextCarId'];

        $topPerspectives = array(10, 1, 7, 8, 11, 12, 2, 4, 13, 5);
        $bottomPerspectives = array(13, 2, 9, 6, 5);

        $carTypeTable = new Car_Types();
        $attributeTable = $this->_getAttributeTable();
        $carParentTable = new Car_Parent();

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

        $attributes = $this->getAttributes(array(
            'zone'      => $specsZoneId,
            'recursive' => true,
            'parent'    => 0
        ));

        $engineTable = $this->_getEngineTable();
        $engineNameAttr = 100;

        $carIds = [];
        foreach ($cars as $car) {
            $carIds[] = $car->id;
        }

        if ($specsZoneId) {
            $this->_loadListOptions($this->_zoneAttrs[$specsZoneId]);
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
                $value = $this->_valueToText($attribute, $value);
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
                $carParentRow = $carParentTable->fetchRow(array(
                    'car_id = ?'    => $car->id,
                    'parent_id = ?' => $contextCarId
                ));
                if ($carParentRow) {
                    $carParentName = $carParentRow->name;
                }
            }

            $result[] = array(
                'id'               => $itemId,
                'name'             => $carParentName ? $carParentName : $car->getFullName($language),
                'beginYear'        => $car->begin_year,
                'endYear'          => $car->end_year,
                'produced'         => $car->produced,
                'produced_exactly' => $car->produced_exactly,
                'topPicture'       => $this->_specPicture($car, $topPerspectives),
                'bottomPicture'    => $this->_specPicture($car, $bottomPerspectives),
                'carType'          => $carType ? $carType->name : null,
                'values'           => $values
            );
        }

        // remove empty attributes
        $this->_removeEmpty($attributes, $result);

        // load units
        $this->_addUnitsToAttributes($attributes);

        return new Project_Spec_Table_Car($result, $attributes);
    }

    public function engineSpecifications($engines, array $options)
    {
        $options = array_merge(array(
            'language' => 'en'
        ), $options);

        $language = $options['language'];

        $attributeTable = $this->_getAttributeTable();

        $result = [];
        $attributes = [];

        $attributes = $this->getAttributes(array(
            'zone'      => self::ENGINE_ZONE_ID,
            'recursive' => true,
            'parent'    => 0
        ));

        foreach ($engines as $engine) {
            $result[] = array(
                'id'      => $engine['id'],
                'name'    => $engine['name'],
                'picture' => $this->_specEnginePicture($engine),
                'values'  => $this->_loadValues($attributes, $engine['id'], self::ITEM_TYPE_ENGINE)
            );
        }

        // remove empty attributes
        $this->_removeEmpty($attributes, $result);

        // load units
        $this->_addUnitsToAttributes($attributes);

        return new Project_Spec_Table_Engine($result, $attributes);
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
            case 1: // строка
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
        if (!isset($this->_userValueDataTables[$type])) {
            $this->_userValueDataTables[$type] = $this->_createUserValueDataTable($type);
        }

        return $this->_userValueDataTables[$type];
    }

    protected function _createUserValueDataTable($type)
    {
        switch ($type) {
            case 1: // строка
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

    protected function _valueToText($attribute, $value)
    {
        if ($value === null) {
            return null;
        }

        switch ($attribute['typeId']) {
            case 1: // строка
                return $value;

            case 2: // int
                return Zend_Locale_Format::toNumber($value);

            case 3: // float
                $options = [];
                if ($attribute['precision']) {
                    $options['precision'] = $attribute['precision'];
                }

                return Zend_Locale_Format::toNumber($value, $options);

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
                                $text[] = $this->_getListOptionsText($attribute['id'], $v);
                            }
                        }
                        return $nullText ? null : implode(', ', $text);
                    } else {
                        return $this->_getListOptionsText($attribute['id'], $value);
                    }
                }
                break;
        }
        return null;
    }

    protected function _calcAvgUserValue($attribute, $itemTypeId, $itemId)
    {
        $userValuesTable = $this->_getUserValueTable();
        $userValuesDataTable = $this->getUserValueDataTable($attribute['typeId']);

        $userValueDataRows = $userValuesDataTable->fetchAll(array(
            'attribute_id = ?' => $attribute['id'],
            'item_id = ?'      => $itemId,
            'item_type_id = ?' => $itemTypeId,
        ));
        if (count($userValueDataRows)) {

            // группируем по пользователям
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

                $row = $userValuesTable->fetchRow(array(
                    'attribute_id = ?' => $attribute['id'],
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId,
                    'user_id = ?'      => $uid
                ));
                if (!$row) {
                    throw new Exception('Строка(строки) данных без дескриптора');
                }

                // ищем такое же значение
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

            // выбираем наибольшее
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

        return array(
            'value' => $actualValue,
            'empty' => $empty
        );
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
            return array(
                'empty' => true,
                'value' => null
            );
        }

        if (!$this->_isEngineAttributeId($attribute['id'])) {
            return array(
                'empty' => true,
                'value' => null
            );
        }

        $carRow = $this->_getCarTable()->fetchRow(array(
            'id = ?' => $itemId
        ));

        if (!$carRow) {
            return array(
                'empty' => true,
                'value' => null
            );
        }

        if (!$carRow->engine_id) {
            return array(
                'empty' => true,
                'value' => null
            );
        }

        $valueDataTable = $this->getValueDataTable($attribute['typeId']);

        if (!$attribute['isMultiple']) {

            $valueDataRow = $valueDataTable->fetchRow(array(
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $carRow->engine_id,
                'item_type_id = ?' => self::ITEM_TYPE_ENGINE,
                'value IS NOT NULL'
            ));

            if ($valueDataRow) {
                return array(
                    'empty' => false,
                    'value' => $valueDataRow->value
                );
            } else {
                return array(
                    'empty' => true,
                    'value' => null
                );
            }

        } else {

            $valueDataRows = $valueDataTable->fetchAll(array(
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $carRow->engine_id,
                'item_type_id = ?' => self::ITEM_TYPE_ENGINE,
                'value IS NOT NULL'
            ));

            if (count($valueDataRows)) {
                $value = [];
                foreach ($valueDataRows as $valueDataRow) {
                    $value[] = $valueDataRow->value;
                }

                return array(
                    'empty' => false,
                    'value' => $value
                );
            } else {
                return array(
                    'empty' => true,
                    'value' => null
                );
            }
        }
    }

    protected function _calcInheritedValue($attribute, $itemTypeId, $itemId)
    {
        $actualValue = array(
            'empty' => true,
            'value' => null
        );

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

                    $valueDataRows = $valueDataTable->fetchAll(array(
                        'attribute_id = ?' => $attribute['id'],
                        'item_id in (?)'   => $parentIds,
                        'item_type_id = ?' => $itemTypeId
                    ));

                    foreach ($valueDataRows as $valueDataRow) {

                        $value = $valueDataRow->value;

                        // ищем такое же значение
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

                    // выбираем наибольшее
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
                        $actualValue = array(
                            'empty' => false,
                            'value' => $registry[$maxValueIdx]
                        );
                    }
                } else {
                    //TODO: multiple attr inheritance
                }
            }

        } else if ($itemTypeId == 3) {

            $engineRow = $this->_getEngineTable()->find($itemId)->current();
            if ($engineRow) {
                $parentEngineRow = $this->_getEngineTable()->find($engineRow->parent_id)->current();

                if ($parentEngineRow) {

                    $valueDataTable = $this->getValueDataTable($attribute['typeId']);

                    if (!$attribute['isMultiple']) {

                        $valueDataRow = $valueDataTable->fetchRow(array(
                            'attribute_id = ?' => $attribute['id'],
                            'item_id = ?'      => $parentEngineRow->id,
                            'item_type_id = ?' => $itemTypeId
                        ));

                        if ($valueDataRow) {
                            $actualValue = array(
                                'empty' => false,
                                'value' => $valueDataRow->value
                            );
                        }

                    } else {

                        $valueDataRows = $valueDataTable->fetchAll(array(
                            'attribute_id = ?' => $attribute['id'],
                            'item_id = ?'      => $parentEngineRow->id,
                            'item_type_id = ?' => $itemTypeId,
                        ));

                        if (count($valueDataRows)) {
                            $a = [];
                            foreach ($valueDataRows as $valueDataRow) {
                                $a[] = $valueDataRow->value;
                            }

                            $actualValue = array(
                                'empty' => false,
                                'value' => $a
                            );
                        }

                    }
                }
            }
        }

        return $actualValue;
    }

    protected function _setActualValue($attribute, $itemTypeId, $itemId, array $actualValue)
    {
        $valueTable = $this->_getValueTable();
        $valueDataTable = $this->getValueDataTable($attribute['typeId']);

        $somethingChanges = false;

        if ($actualValue['empty']) {

            // descriptor
            $row = $valueTable->fetchRow(array(
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $itemId,
                'item_type_id = ?' => $itemTypeId
            ));
            if ($row) {
                $row->delete();
                $somethingChanges = true;
            }

            // value
            $rows = $valueDataTable->fetchAll(array(
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $itemId,
                'item_type_id = ?' => $itemTypeId
            ));
            foreach ($rows as $row) {
                $row->delete();
                $somethingChanges = true;
            }
        } else {

            // descriptor
            $valueRow = $valueTable->fetchRow(array(
                'attribute_id = ?' => $attribute['id'],
                'item_id = ?'      => $itemId,
                'item_type_id = ?' => $itemTypeId
            ));
            if (!$valueRow) {
                $valueRow = $valueTable->createRow(array(
                    'attribute_id' => $attribute['id'],
                    'item_id'      => $itemId,
                    'item_type_id' => $itemTypeId,
                    'update_date'  => new Zend_Db_Expr('now()')
                ));
                $valueRow->save();
                $somethingChanges = true;
            }

            // value
            if ($attribute['isMultiple']) {
                $rows = $valueDataTable->fetchAll(array(
                    'attribute_id = ?' => $attribute['id'],
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId
                ));
                foreach ($rows as $row) {
                    $row->delete();
                    $somethingChanges = true;
                }

                foreach ($actualValue['value'] as $ordering => $value) {
                    $rows = $valueDataTable->insert(array(
                        'attribute_id' => $attribute['id'],
                        'item_id'      => $itemId,
                        'item_type_id' => $itemTypeId,
                        'ordering'     => $ordering,
                        'value'        => $value
                    ));
                    $somethingChanges = true;
                }

            } else {
                $row = $valueDataTable->fetchRow(array(
                    'attribute_id = ?' => $attribute['id'],
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId,
                ));
                if (!$row) {
                    $row = $valueDataTable->createRow(array(
                        'attribute_id' => $attribute['id'],
                        'item_id'      => $itemId,
                        'item_type_id' => $itemTypeId,
                        'value'        => $actualValue['value']
                    ));
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
        $valueTable = $this->_getValueTable();
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

        $valueTable = $this->_getValueTable();
        $db = $valueTable->getAdapter();
        $select = $db->select()
            ->from($valueTable->info('name'), array('twins_groups_cars.twins_group_id', new Zend_Db_Expr('1')))
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
        $table = $this->_getValueTable();
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
            $valueTable = $this->_getValueTable();
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
                $haveValue = $this->_haveOwnAttributeValue($attribute['id'], $itemTypeId, $itemId);
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

        $uvTable = $this->_getUserValueTable();
        $db = $uvTable->getAdapter();

        $pairs = $db->fetchPairs(
            $db->select(true)
                ->from($uvTable->info('name'), array('user_id', 'c' => new Zend_Db_Expr('COUNT(1)')))
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
            case 1: // строка
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

        $attributes = $this->getAttributes(array(
            'zone'   => $zoneId,
            'parent' => null
        ));

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

    public function getUserValueText($attributeId, $itemTypeId, $itemId, $userId)
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
            $values[] = $this->_valueToText($attribute, $row->value);
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

    public function getActualValueText($attributeId, $itemTypeId, $itemId)
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
                $text[] = $this->_valueToText($attribute, $v);
            }
            return implode(', ', $text);

        } else {

            return $this->_valueToText($attribute, $value);
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

        $requests = array(
            1 => false,
            2 => false, /* , 5*/
            3 => false,
            //4 => array(false),
            6 => true, /* , 7 */
        );

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

        $attributes = $this->getAttributes(array(
            'zone'   => $zoneId,
            'parent' => null
        ));

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

        $attributes = $this->getAttributes(array(
            'zone'   => $zoneId,
            'parent' => null
        ));

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
        if ($this->_types === null) {
            $this->_types = [];
            foreach ($this->_getTypeTable()->fetchAll() as $row) {
                $this->_types[(int)$row->id] = [
                    'id'        => (int)$row->id,
                    'name'      => $row->name,
                    'element'   => $row->element,
                    'maxlength' => $row->maxlength,
                    'size'      => $row->size
                ];
            }
        }

        if (!isset($this->_types[$typeId])) {
            throw new Exception("Type `$typeId` not found");
        }

        return $this->_types[$typeId];
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

        $userValueTable = $this->_getUserValueTable();
        $userValueRows = $userValueTable->fetchAll(array(
            'attribute_id = ?' => $attribute['id'],
            'item_id = ?'      => $itemId,
            'item_type_id = ?' => $itemTypeId
        ));

        $userValues = [];
        $uniqueValues = [];
        foreach ($userValueRows as $userValueRow) {
            $v = $this->getUserValue($attribute['id'], $itemTypeId, $itemId, $userValueRow['user_id']);
            $serializedValue = serialize($v);
            $uniqueValues[] = $serializedValue;
            $userValues[$userValueRow['user_id']] = array(
                'value' => $serializedValue,
                'date'  => $userValueRow['update_date']
            );
        }

        $uniqueValues = array_unique($uniqueValues);
        $hasConflict = count($uniqueValues) > 1;

        $valueRow = $this->_getValueTable()->fetchRow(array(
            'attribute_id = ?' => $attribute['id'],
            'item_id = ?'      => $itemId,
            'item_type_id = ?' => $itemTypeId
        ));

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

                $affectedRows = $userValueTable->update(array(
                    'conflict' => $conflict,
                    'weight'   => $weight
                ), array(
                    'user_id = ?'      => $userId,
                    'attribute_id = ?' => $attributeId,
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId
                ));

                if ($affectedRows) {
                    $affectedUserIds[] = $userId;
                }
            }
        } else {
            $affectedRows = $userValueTable->update(array(
                'conflict' => 0,
                'weight'   => self::WEIGHT_NONE
            ), array(
                'attribute_id = ?' => $attributeId,
                'item_id = ?'      => $itemId,
                'item_type_id = ?' => $itemTypeId
            ));

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
            $userValueTable = $this->_getUserValueTable();
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

            $db->update('users', array(
                'specs_weight' => $expr,
            ), array(
                'id IN (?)' => $userId
            ));
        }

    }

    public function refreshConflictFlags()
    {
        $valueTable = $this->_getValueTable();
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
        $valueTable = $this->_getUserValueTable();
        $select = $valueTable->select(true)
            ->where('attrs_user_values.item_id = ?', (int)$itemId)
            ->where('attrs_user_values.item_type_id = ?', (int)$typeId);

        foreach ($valueTable->fetchAll($select) as $valueRow) {
            //print $valueRow['attribute_id'] . '#' . $valueRow['item_type_id'] . '#' . $valueRow['item_id'] . PHP_EOL;
            $this->refreshConflictFlag($valueRow['attribute_id'], $valueRow['item_type_id'], $valueRow['item_id']);
        }
    }

    public function getConflicts($userId, $filter, $page, $perPage)
    {
        $userId = (int)$userId;

        $valueTable = $this->_getValueTable();
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

        $userValueTable = $this->_getUserValueTable();

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage($perPage)
            ->setCurrentPageNumber($page);

        $conflicts = [];
        foreach ($paginator->getCurrentItems() as $valueRow) {

            // other users values
            $userValueRows = $userValueTable->fetchAll(array(
                'attribute_id = ?' => $valueRow['attribute_id'],
                'item_id = ?'      => $valueRow['item_id'],
                'item_type_id = ?' => $valueRow['item_type_id'],
                'user_id <> ?'     => $userId
            ));

            $values = [];
            foreach ($userValueRows as $userValueRow) {
                $values[] = array(
                    'value'  => $this->getUserValueText(
                        $userValueRow['attribute_id'],
                        $userValueRow['item_type_id'],
                        $userValueRow['item_id'],
                        $userValueRow['user_id']
                    ),
                    'userId' => $userValueRow['user_id']
                );
            }

            // my value
            $userValueRow = $userValueTable->fetchRow(array(
                'attribute_id = ?' => $valueRow['attribute_id'],
                'item_id = ?'      => $valueRow['item_id'],
                'item_type_id = ?' => $valueRow['item_type_id'],
                'user_id = ?'      => $userId
            ));
            $value = null;
            if ($userValueRow) {
                $value = $this->getUserValueText(
                    $userValueRow['attribute_id'],
                    $userValueRow['item_type_id'],
                    $userValueRow['item_id'],
                    $userValueRow['user_id']
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
                $attributeName[] = $cAttr['name'];
                $cAttr = $this->_getAttribute($cAttr['parentId']);
            } while ($cAttr);

            $conflicts[] = array(
                'itemId'     => $valueRow['item_id'],
                'itemTypeId' => $valueRow['item_type_id'],
                'attribute'  => implode(' / ', array_reverse($attributeName)),
                'unit'       => $unit,
                'values'     => $values,
                'value'      => $value
            );
        }

        return array(
            'conflicts' => $conflicts,
            'paginator' => $paginator
        );
    }

    public function refreshUserConflictsStat()
    {
        $userValueTable = $this->_getUserValueTable();
        $db = $userValueTable->getAdapter();

        $userIds = $db->fetchCol(
            $db->select()
                ->distinct()
                ->from($userValueTable->info('name'), array('user_id'))
        );

        $this->refreshUserConflicts($userIds);
    }

    public function refreshUsersConflictsStat()
    {
        $userValueTable = $this->_getUserValueTable();
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

        $db->update('users', array(
            'specs_weight' => $expr,
        ));
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
        if (!array_key_exists($userId, $this->_valueWeights)) {
            $userRow = $this->_getUserTable()->find($userId)->current();
            if ($userRow) {
                $this->_valueWeights[$userId] = $userRow->specs_weight;
            } else {
                $this->_valueWeights[$userId] = 1;
            }
        }

        return $this->_valueWeights[$userId];
    }
}