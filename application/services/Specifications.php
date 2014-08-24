<?php

class Application_Service_Specifications
{
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
     * @var Attrs_Units
     */
    protected $_unitTable = null;

    protected $_valueTables = array();

    protected $_userValueDataTables = array();

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

    /**
     * @var Car_Parent
     */
    protected $_carParentTable = null;

    /**
     * @var array
     */
    protected $_carChildsCache = array();

    /**
     * @var Attrs_Values
     */
    protected $_valueTable = null;

    /**
     * @var array
     */
    protected $_actualValueCache = array();

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
            $this->_zones = array();
            foreach ($zoneTable->fetchAll() as $zone) {
                $this->_zones[$zone->id] = $zone;
            }
        }

        if (!isset($this->_zones[$id])) {
            throw new Exception("Zone `$id` not found");
        }

        return $this->_zones[$id];
    }

    protected function _getUnit($id)
    {
        $id = (int)$id;

        if ($this->_units === null) {
            $units = array();
            foreach ($this->_getUnitTable()->fetchAll() as $unit) {
                $units[$unit->id] = array(
                    'name' => $unit->name,
                    'abbr' => $unit->abbr
                );
            }

            $this->_units = $units;
        }

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

    protected function _getForm($itemId, $zoneId, Users_Row $user, array $options)
    {
        $options = array_merge($options, array(
            'user'   => $user,
            'zone'   => $this->_getZone($zoneId),
            'itemId' => $itemId,
        ));

        return new Application_Form_Attrs_Zone_Attributes($options);
    }

    /**
     * @param Cars_Row $car
     * @param array $options
     * @return Application_Form_Attrs_Zone_Attributes
     */
    public function getCarForm(Cars_Row $car, Users_Row $user, array $options)
    {
        $zoneId = $this->_zoneIdByCarTypeId($car->car_type_id);
        return $this->_getForm($car->id, $zoneId, $user, $options);
    }

    public function getEngineForm(Engines_Row $engine, Users_Row $user, array $options)
    {
        $zoneId = 5;
        return $this->_getForm($engine->id, $zoneId, $user, $options);
    }

    public function getEquipeForm(Equipes_Row $equipe, Users_Row $user, array $options)
    {
        $zoneId = 4;
        return $this->_getForm($equipe->id, $zoneId, $user, $options);
    }

    protected function _collectFormData($zone, $attributes, $values)
    {
        $result = array();
        foreach ($attributes as $attribute) {
            $nodeName = 'attr_' . $attribute->id;
            $subAttributes = $zone->findAttributes($attribute);
            if (count($subAttributes)) {
                $subvalues = $this->_collectFormData(
                    $zone,
                    $zone->findAttributes($attribute),
                    $values[$nodeName]
                );
                foreach ($subvalues as $id => $value) {
                    $result[$id] = $value;
                }
            } else {
                $value = $values[$nodeName];
                /*switch ($attribute->type_id) {
                 case 3:
                if (strlen($value)) {
                $value = Zend_Locale_Format::getFloat($value, array(
                    'locale' => Zend_Registry::get('Zend_Locale')
                ));
                } else {
                $value = null;
                }
                break;
                default:
                break;
                }*/

                $result[$attribute->id] = $value;
            }
        }

        return $result;
    }

    protected function _getAttributeRow($id)
    {
        if ($this->_attributeRows === null) {
            $attributeTable = $this->_getAttributeTable();
            $array = array();
            foreach ($attributeTable->fetchAll() as $row) {
                $array[$row->id] = $row;
            }

            $this->_attributeRows = $array;
        }

        if (!isset($this->_attributeRows[$id])) {
            throw new Exception("Аттрибут $id не найден");
        }

        return $this->_attributeRows[$id];
    }

    public function saveAttrsZoneAttributes($form)
    {
        $values = $form->getValues();

        $zone = $form->getZone();
        $itemId = $form->getItemId();

        $values = $this->_collectFormData($zone, $zone->findAttributes(), $values);
        $userValueTable = $this->_getUserValueTable();
        $itemTypeId = $zone->item_type_id;

        $user = $form->getUser();

        $uid = $user->id;

        foreach ($values as $attribute_id => $value) {
            $attribute = $this->_getAttributeRow($attribute_id);
            $somethingChanged = false;

            $userValueDataTable = $attribute->getUserValueTable();

            if ($attribute->isMultiple()) {

                // удаляем дескрипторы значений
                $userValues = $userValueTable->fetchAll(array(
                    'attribute_id = ?' => $attribute->id,
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId,
                    'user_id = ?'      => $uid,
                ));
                foreach ($userValues as $userValue) {
                    $userValue->delete();
                }
                // удаляем значение
                $userValueDataRows = $userValueDataTable->fetchAll(array(
                    'attribute_id = ?' => $attribute->id,
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId,
                    'user_id = ?'      => $uid
                ));
                foreach ($userValueDataRows as $userValueDataRow) {
                    $userValueDataRow->delete();
                }

                if ($value) {

                    $empty = true;
                    foreach ($value as $oneValue) {
                        if ($oneValue) {
                            $empty = false;
                            break;
                        }
                    }

                    if (!$empty) {
                        // вставляем новые дексрипторы и значения
                        $userValueTable->insert(array(
                            'attribute_id' => $attribute->id,
                            'item_id'      => $itemId,
                            'item_type_id' => $itemTypeId,
                            'user_id'      => $uid,
                            'add_date'     => new Zend_Db_Expr('NOW()'),
                            'update_date'  => new Zend_Db_Expr('NOW()'),
                        ));
                        $ordering = 1;
                        foreach ($value as $oneValue) {
                            if ($oneValue) {
                                $userValueDataTable->insert(array(
                                    'attribute_id' => $attribute->id,
                                    'item_id'      => $itemId,
                                    'item_type_id' => $itemTypeId,
                                    'user_id'      => $uid,
                                    'ordering'     => $ordering,
                                    'value'        => $oneValue
                                ));
                            }

                            $ordering++;
                        }
                    }
                }

                $somethingChanged = $this->_updateActualValue($attribute, $itemTypeId, $itemId);

            } else {

                if (strlen($value) > 0) {
                    // вставлям/обновляем дескриптор значения
                    $userValue = $userValueTable->fetchRow(array(
                        'attribute_id = ?' => $attribute->id,
                        'item_id = ?'      => $itemId,
                        'item_type_id = ?' => $itemTypeId,
                        'user_id = ?'      => $uid
                    ));
                    if (!$userValue) {
                        $userValue = $userValueTable->createRow(array(
                            'attribute_id' => $attribute->id,
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

                    // вставляем/обновляем значение
                    $userValueData = $userValueDataTable->fetchRow(array(
                        'attribute_id = ?' => $attribute->id,
                        'item_id = ?'      => $itemId,
                        'item_type_id = ?' => $itemTypeId,
                        'user_id = ?'      => $uid
                    ));
                    if (!$userValueData) {
                        $userValueData = $userValueDataTable->fetchNew();
                        $userValueData->setFromArray(array(
                            'attribute_id' => $attribute->id,
                            'item_id'      => $itemId,
                            'item_type_id' => $itemTypeId,
                            'user_id'      => $uid
                        ));
                    }
                    $userValueData->value = $value;
                    $userValueData->save();

                    $somethingChanged = $this->_updateActualValue($attribute, $itemTypeId, $itemId);

                } else {

                    $needUpdate = false;
                    // удаляем дескриптор значения
                    $userValue = $userValueTable->fetchRow(array(
                        'attribute_id = ?' => $attribute->id,
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
                        'attribute_id = ?' => $attribute->id,
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
            }
        }
    }

    protected function _getChildItemIds($parentId)
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

    protected function _haveOwnAttributeValue($attribute, $itemTypeId, $itemId)
    {
        return (bool)$this->_getUserValueTable()->fetchRow(array(
            'attribute_id = ?' => $attribute->id,
            'item_type_id = ?' => $itemTypeId,
            'item_id = ?'      => $itemId
        ));
    }

    protected function _propagateInheritance($attribute, $itemTypeId, $itemId)
    {
        if ($itemTypeId == 1) {

            $userValueTable = $this->_getUserValueTable();

            $childIds = $this->_getChildItemIds($itemId);

            foreach ($childIds as $childId) {
                // update only if row use inheritance
                $haveValue = $this->_haveOwnAttributeValue($attribute, $itemTypeId, $childId);

                if (!$haveValue) {

                    $value = $this->_calcInheritedValue($attribute, $itemTypeId, $childId);
                    $changed = $this->_setActualValue($attribute, $itemTypeId, $childId, $value);
                    if ($changed) {
                        $this->_propagateInheritance($attribute, $itemTypeId, $childId);
                    }
                }
            }
        }
    }

    protected function _specPicture($car, $perspectives)
    {
        $pictureTable = new Picture();
        $pictureTableAdapter = $pictureTable->getAdapter();

        $order = array();
        foreach ($perspectives as $pid) {
            $order[] = new Zend_Db_Expr($pictureTableAdapter->quoteInto('pictures.perspective_id = ? DESC', $pid));
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

    protected function _getAttributes($parent = null)
    {
        $select = $this->_getAttributeTable()->select(true)
            ->order('position');
            /*->join('attrs_values', 'attrs_attributes.id = attrs_values.attribute_id', null)
            ->where('attrs_values.item_id in (?)', $itemIds)
            ->where('attrs_values.item_type_id = 1') //TODO: parametrize
            ->group('attrs_attributes.id');*/

        if ($parent) {
            $select->where('parent_id = ?', $parent->id);
        } else {
            $select->where('parent_id is null');
        }

        $rows = $this->_getAttributeTable()->fetchAll($select);

        $result = array();
        foreach ($rows as $row) {
            $result[] = array(
                'id'         => $row->id,
                'name'       => $row->name,
                'typeId'     => $row->type_id,
                'unitId'     => $row->unit_id,
                'isMultiple' => $row->isMultiple(),
                'precision'  => $row->precision,
                'childs'     => $this->_getAttributes($row)
            );
        }

        return $result;
    }

    public function getActualValue($attribute, $itemId, $itemTypeId)
    {
        if (!$itemId) {
            throw new Exception("Item_id not set");
        }

        //if (!isset($this->_actualValueCache[]))

        $valuesTable = $this->_getValueDataTable($attribute['typeId']);
        if (!$valuesTable) {
            return null;
        }

        $select = $valuesTable->select(true)
            ->where('attribute_id = ?', $attribute['id'])
            ->where('item_id = ?', $itemId)
            ->where('item_type_id = ?', $itemTypeId);

        if ($attribute['isMultiple']) {

            $select->order('ordering');

            $rows = $valuesTable->fetchAll($select);

            $values = array();
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

    protected function _loadValues($attributes, $itemId, $itemTypeId)
    {
        $values = array();
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
            'language' => 'en'
        ), $options);

        $language = $options['language'];

        $topPerspectives = array(10, 1, 7, 8, 11, 12, 2, 4, 13, 5);
        $bottomPerspectives = array(13, 2, 9, 6, 5);

        $carTypeTable = new Car_Types();
        $attributeTable = $this->_getAttributeTable();

        $ids = array();
        foreach ($cars as $car) {
            $ids[] = $car->id;
        }

        $attributes = $this->_getAttributes();

        $result = array();

        foreach ($cars as $car) {

            $carType = $carTypeTable->find($car->car_type_id)->current();

            /*$attributeRows = $attributeTable->fetchAll(array(
                'parent_id is null'
            ));

            foreach ($attributeRows as $attribute) {
                $rows = array();

                $cellGroups = $this->_buildCellGroups($attribute, $carItemType, $cars);
                $rows[] = array($attribute->name, $cellGroups);
                $cellGroups = $this->_buildCellGroups($attribute, $equipeItemType, $cars);
                $rows[] = array($attribute->name, $cellGroups);

                //$html .= $this->getRowHtml($attribute->name, $cellGroups, true);
                $html .= $this->PrintSection($attribute->name, $rows, $totalColumns);
            }*/

            $result[] = array(
                'id'               => $car->id,
                'name'             => $car->getFullName($language),
                'produced'         => $car->produced,
                'produced_exactly' => $car->produced_exactly,
                'topPicture'       => $this->_specPicture($car, $topPerspectives),
                'bottomPicture'    => $this->_specPicture($car, $bottomPerspectives),
                'carType'          => $carType ? $carType->name : null,
                'values'           => $this->_loadValues($attributes, $car->id, 1)
            );
        }

        // remove empty attributes
        $this->_removeEmpty($attributes, $result);

        // load units
        $this->_addUnitsToAttributes($attributes);

        return new Project_Spec_Table($result, $attributes);
    }

    protected function _addUnitsToAttributes(&$attributes)
    {
        foreach ($attributes as &$attribute) {
            if ($attribute['unitId']) {
                $attribute['unit'] = $this->_getUnit($attribute['unitId']);
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

    protected function _getValueDataTable($type)
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
                return new Attrs_Values_Text();

            case 5: // checkbox
                return new Attrs_Values_Int();

            case 6: // select
                return new Attrs_Values_List();

            case 7: // select
                return new Attrs_Values_List();
        }
        return null;
    }

    protected function _getUserValueDataTable($type)
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
                return new Attrs_User_Values_Text();

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
                $options = array();
                if ($attribute['precision']) {
                    $options['precision'] = $attribute['precision'];
                }

                return Zend_Locale_Format::toNumber($value, $options);

            case 4: // textarea
                return $value;

            case 5: // checkbox
                return is_null($value) ? null : ($value ? 'да' : 'нет');

            case 6: // select
                if ($value) {
                    $row = $this->_getListOptionsTable()->find($value)->current();
                    if ($row) {
                        return $row->name;
                    }
                }
                break;

            case 7: // select
                if ($value) {
                    $row = $this->_getListOptionsTable()->find($value)->current();
                    if ($row) {
                        return $row->name;
                    }
                }
                break;
        }
        return null;
    }

    protected function _calcAvgUserValue($attribute, $itemTypeId, $itemId)
    {
        //$uTable = new Users();
        $userValuesTable = $this->_getUserValueTable();
        $userValuesDataTable = $this->_getUserValueDataTable($attribute->type_id);

        $userValueDataRows = $userValuesDataTable->fetchAll(array(
            'attribute_id = ?' => $attribute->id,
            'item_id = ?'      => $itemId,
            'item_type_id = ?' => $itemTypeId,
            'value IS NOT NULL'
        ));
        if (count($userValueDataRows)) {

            // группируем по пользователям
            $data = array();
            foreach ($userValueDataRows as $userValueDataRow) {
                $uid = $userValueDataRow->user_id;
                if (!isset($data[$uid])) {
                    $data[$uid] = array();
                }
                $data[$uid][] = $userValueDataRow;
            }

            $idx = 0;
            $registry = $freshness = $ratios = array();
            foreach ($data as $uid => $valueRows) {
                /*$user = $uTable->find($uid)->current();
                if (!$user) {
                    throw new Exception('User not found');
                }*/

                if ($attribute->isMultiple()) {
                    $value = array();
                    foreach ($valueRows as $valueRow) {
                        $value[$valueRow->ordering] = $valueRow->value;
                    }
                } else {
                    foreach ($valueRows as $valueRow) {
                        $value = $valueRow->value;
                    }
                }

                $row = $userValuesTable->fetchRow(array(
                    'attribute_id = ?' => $attribute->id,
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

                //$registry[$idx] = $valueRow->value;

                if (!isset($ratios[$matchRegIdx])) {
                    $ratios[$matchRegIdx] = 0;
                    $freshness[$matchRegIdx] = null;
                }
                $ratios[$matchRegIdx] += 1; // $user->getExpertLevel()
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

        } else {
            $actualValue = null;
        }

        return $actualValue;
    }

    protected function _calcInheritedValue($attribute, $itemTypeId, $itemId)
    {
        $actualValue = null;

        if ($itemTypeId == 1) {

            $valueDataTable = $this->_getValueDataTable($attribute->type_id);
            $db = $valueDataTable->getAdapter();

            $parentIds = $db->fetchCol(
                $db->select()
                    ->from('car_parent', 'parent_id')
                    ->where('car_id = ?', $itemId)
            );

            if (count($parentIds) > 0) {

                if (!$attribute->isMultiple()) {
                    $idx = 0;
                    $registry = array();
                    $ratios = array();

                    $valueDataRows = $valueDataTable->fetchAll(array(
                        'attribute_id = ?' => $attribute->id,
                        'item_id in (?)'   => $parentIds,
                        'item_type_id = ?' => $itemTypeId,
                        'value IS NOT NULL'
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
                            $freshness[$matchRegIdx] = null;
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
                        $actualValue = $registry[$maxValueIdx];
                    }
                } else {
                    //TODO: multiple attr inheritance
                }
            }
        }

        return $actualValue;
    }

    protected function _setActualValue($attribute, $itemTypeId, $itemId, $actualValue)
    {
        $valueTable = $this->_getValueTable();
        $valueDataTable = $this->_getValueDataTable($attribute->type_id);

        $somethingChanges = false;

        if ($actualValue === null) {

            // descriptor
            $row = $valueTable->fetchRow(array(
                'attribute_id = ?' => $attribute->id,
                'item_id = ?'      => $itemId,
                'item_type_id = ?' => $itemTypeId
            ));
            if ($row) {
                $row->delete();
                $somethingChanges = true;
            }

            // value
            $rows = $valueDataTable->fetchAll(array(
                'attribute_id = ?' => $attribute->id,
                'item_id = ?'      => $itemId,
                'item_type_id = ?' => $itemTypeId
            ));
            foreach ($rows as $row) {
                $row->delete();
                $somethingChanges = true;
            }
        } else {

            // descriptor
            $row = $valueTable->fetchRow(array(
                'attribute_id = ?' => $attribute->id,
                'item_id = ?'      => $itemId,
                'item_type_id = ?' => $itemTypeId
            ));
            if (!$row) {
                $row = $valueTable->createRow(array(
                    'attribute_id' => $attribute->id,
                    'item_id'      => $itemId,
                    'item_type_id' => $itemTypeId
                ));
                $row->save();
                $somethingChanges = true;
            }

            // value
            if ($attribute->isMultiple()) {
                $rows = $valueDataTable->fetchAll(array(
                    'attribute_id = ?' => $attribute->id,
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId
                ));
                foreach ($rows as $row) {
                    $row->delete();
                    $somethingChanges = true;
                }

                foreach ($actualValue as $ordering => $value) {
                    $rows = $valueDataTable->insert(array(
                        'attribute_id' => $attribute->id,
                        'item_id'      => $itemId,
                        'item_type_id' => $itemTypeId,
                        'ordering'     => $ordering,
                        'value'        => $value
                    ));
                    $somethingChanges = true;
                }

            } else {
                $row = $valueDataTable->fetchRow(array(
                    'attribute_id = ?' => $attribute->id,
                    'item_id = ?'      => $itemId,
                    'item_type_id = ?' => $itemTypeId,
                ));
                if (!$row) {
                    $row = $valueDataTable->createRow(array(
                        'attribute_id' => $attribute->id,
                        'item_id'      => $itemId,
                        'item_type_id' => $itemTypeId,
                        'value'        => $actualValue
                    ));
                    $row->save();
                    $somethingChanges = true;
                } elseif ($row->value != $actualValue) {
                    $row->value = $actualValue;
                    $row->save();
                    $somethingChanges = true;
                }

            }
        }

        return $somethingChanges;
    }

    protected function _updateActualValue($attribute, $itemTypeId, $itemId)
    {
        $actualValue = $this->_calcAvgUserValue($attribute, $itemTypeId, $itemId);

        if ($actualValue === null) {
            $actualValue = $this->_calcInheritedValue($attribute, $itemTypeId, $itemId);
        }

        return $this->_setActualValue($attribute, $itemTypeId, $itemId, $actualValue);

    }

    public function hasSpecs($itemTypeId, $itemId)
    {
        return (bool)$this->_getValueTable()->fetchRow(array(
            'item_id = ?'      => $itemId,
            'item_type_id = ?' => $itemTypeId
        ));
    }

    public function hasChildSpecs($itemTypeId, $itemId)
    {
        if ($itemTypeId == 1) {
            $table = $this->_getValueTable();
            //var_dump($itemTypeId, $itemId);
            return (bool)$table->fetchRow(
                $table->select(true)
                    ->where('attrs_values.item_type_id = ?', $itemTypeId)
                    ->join('car_parent', 'attrs_values.item_id = car_parent.car_id', null)
                    ->where('car_parent.parent_id = ?', $itemId)
            );
        }

        return false;

    }

    public function updateActualValues($itemTypeId, $itemId)
    {
        foreach ($this->_getAttributeTable()->fetchAll() as $attribute) {
            if ($attribute->type_id) {
                $this->_updateActualValue($attribute, $itemTypeId, $itemId);
            }
        }
    }

    public function updateInheritedValues($itemTypeId, $itemId)
    {
        foreach ($this->_getAttributeTable()->fetchAll() as $attribute) {
            if ($attribute->type_id) {
                $haveValue = $this->_haveOwnAttributeValue($attribute, $itemTypeId, $itemId);
                if (!$haveValue) {
                    $this->_updateActualValue($attribute, $itemTypeId, $itemId);
                }
            }
        }
    }
}