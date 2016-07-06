<?php

class Application_Form_Attrs_Zone_Attributes extends Project_Form
{
    private $_zone = null;

    private $_itemType = null;

    private $_itemId = null;

    /**
     * @var Application_Service_Specifications
     */
    private $_service = null;

    private $_allValues = null;

    /**
     * @var array
     */
    private $_actualValues = [];

    /**
     * @var array
     */
    private $_multioptions = [];

    private $_editableAttributes = [];

    private $_editOnlyMode = false;

    public function setZone($zone)
    {
        $this->_zone = $zone;

        return $this;
    }

    public function getZone()
    {
        return $this->_zone;
    }

    public function setEditOnlyMode($editOnlyMode)
    {
        $this->_editOnlyMode = (bool)$editOnlyMode;

        return $this;
    }

    public function setEditableAttributes(array $editableAttributes)
    {
        $this->_editableAttributes = (array)$editableAttributes;

        return $this;
    }

    /**
     * @param array $multioptions
     * @return Application_Form_Attrs_Zone_Attributes
     */
    public function setMultioptions(array $multioptions)
    {
        $this->_multioptions = $multioptions;

        return $this;
    }

    /**
     * @param array $value
     * @return Application_Form_Attrs_Zone_Attributes
     */
    public function setAllValues(array $value)
    {
        $this->_allValues = $value;

        return $this;
    }

    /**
     * @param array $value
     * @return Application_Form_Attrs_Zone_Attributes
     */
    public function setActualValues(array $value)
    {
        $this->_actualValues = $value;

        return $this;
    }

    public function setItemId($id)
    {
        $this->_itemId = $id;

        return $this;
    }

    public function getItemId()
    {
        return $this->_itemId;
    }

    public function setOptions(array $options)
    {
        $options['decorators'] = array(
            'FormElements',
            'PrepareElements',
            array('viewScript', array(
                'viewScript' => 'forms/specs.phtml',
                'placement'  => false
            )),
            'Form'
        );
        $options['disableLoadDefaultDecorators'] = true;

        parent::setOptions($options);

        $this->_itemType = $this->_zone->findParentAttrs_Item_Types();
    }

    /**
     * @param Application_Service_Specifications $service
     * @return Application_Form_Attrs_Zone_Attributes
     */
    public function setService(Application_Service_Specifications $service)
    {
        $this->_service = $service;

        return $this;
    }

    private function buildElements($form, $attributes, $deep, $parents = [])
    {
        if (!$this->_itemId) {
            throw new Exception('item_id not set');
        }

        if (!$this->_itemType) {
            throw new Exception('$this->_itemType not set');
        }

        foreach ($attributes as $attribute) {
            $subAttributes = $this->_service->getAttributes([
                'parent' => $attribute['id'],
                'zone'   => $this->_zone->id
            ]);

            $nodeName = 'attr_' . $attribute['id'];
            if (count($subAttributes)) {
                $subform = new Zend_Form_SubForm([
                    'legend'                       => $attribute['name'],
                    'description'                  => $attribute['description'],
                    'disableLoadDefaultDecorators' => true,
                    'elementsBelongTo'             => null,
                    'decorators'                   => [
                        'FormElements',
                        ['viewScript', [
                            'viewScript' => 'forms/specs-sub.phtml',
                            'placement'  => false,
                            'attribute'  => $attribute,
                            'deep'       => $deep,
                            'classes'    => $parents
                        ]],
                    ]
                ]);
                $form->addSubForm($subform, $nodeName);
                $this->buildElements($subform, $subAttributes, $deep + 1,
                    array_merge($parents, ['subform-' . $attribute['id']]));
            } else {

                $options = $this->getFormElementOptions($attribute, $deep, $parents);

                $type = null;
                if ($attribute['typeId']) {
                    $type = $this->_service->getType($attribute['typeId']);
                }

                $elementType = $type['element'];
                if ($type['element'] == 'select' && $attribute['isMultiple']) {
                    $elementType = 'multiselect';
                }

                $form->addElement($elementType, $nodeName, $options);
            }
        }
    }

    private function getFormElementOptions($attribute, $deep, $parents)
    {
        if (!$this->_itemId) {
            throw new Exception('item_id not set');
        }

        if (!$this->_itemType) {
            throw new Exception('$this->_itemType not set');
        }

        $valueExists = array_key_exists($attribute['id'], $this->_actualValues);
        if ($valueExists) {
            $value = $this->_actualValues[$attribute['id']];
        } else {
            $value = null;
        }

        $unit = $this->_service->getUnit($attribute['unitId']);

        $allValues = array();
        if (isset($this->_allValues[$attribute['id']])) {
            $allValues = $this->_allValues[$attribute['id']];
        }

        $readonly = false;
        if ($this->_editOnlyMode) {
            $readonly = !in_array($attribute['id'], $this->_editableAttributes);
        }

        $options = [
            'required'                     => false,
            'label'                        => $attribute['name'],
            'disableLoadDefaultDecorators' => true,
            'description'                  => $attribute['description'],
            'class'                        => 'input-sm form-control',
            'disabled'                     => $readonly ? 'disabled' : null,
            'decorators'                   => [
                'ViewHelper',
                ['viewScript', [
                    'viewScript'   => 'forms/specs-element.phtml',
                    'placement'    => false,
                    'deep'         => $deep,
                    'classes'      => $parents,
                    'actualExists' => $valueExists,
                    'actual'       => $value,
                    'unit'         => $unit,
                    'allValues'    => $allValues
                ]]
            ]
        ];

        $type = null;
        if ($attribute['typeId']) {
            $type = $this->_service->getType($attribute['typeId']);
        }

        if ($type) {
            if ($type['maxlength']) {
                $options['maxlength'] = $type['maxlength'];
            }
            if ($type['size']) {
                $options['size'] = $type['size'];
            }

            switch ($type['id']) {
                case 1: // строка
                    $options['filters'] = array('StringTrim');
                    break;

                case 2: // int
                    $options['filters'] = array('StringTrim');
                    $options['validators'] = array(
                        array('Attrs_IntOrNull', false, array('en_US'))
                    );
                    break;

                case 3: // float
                    $options['filters'] = array('StringTrim');
                    $options['validators'] = array(
                        array('Attrs_FloatOrNull', false, array('en_US'))
                    );
                    break;

                case 4: // textarea
                    $options['filters'] = array('StringTrim');
                    break;

                case 5: // checkbox
                    $options['multioptions'] = array(
                        ''  => '—',
                        '-' => 'нет значения',
                        '0' => 'нет',
                        '1' => 'да'
                    );
                    break;

                case 6: // select
                case 7: // treeselect
                    $multioptions = array(
                        ''  => '—',
                        '-' => 'нет значения',
                    );
                    if (isset($this->_multioptions[$attribute['id']])) {
                        $multioptions = array_replace($multioptions, $this->_multioptions[$attribute['id']]);
                    }
                    $options['multioptions'] = $multioptions;
                    break;
            }
        }

        return $options;
    }

    public function init()
    {
        parent::init();

        $this->setMethod('post');

        // выбираем дерево редактируемых характеристик
        $attributes = $this->_service->getAttributes(array(
            'parent' => 0,
            'zone'   => $this->_zone->id
        ));
        $this->buildElements($this, $attributes, 0);
    }
}