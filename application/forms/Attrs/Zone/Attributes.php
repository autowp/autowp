<?php

class Application_Form_Attrs_Zone_Attributes extends Project_Form
{
    protected $_zone = null;

    protected $_itemType = null;

    protected $_itemId = null;

    /**
     * @var Application_Service_Specifications
     */
    protected $_service = null;

    protected $_allValues = null;

    /**
     * @var array
     */
    protected $_actualValues = array();

    /**
     * @var array
     */
    protected $_multioptions = array();

    public function setZone($zone)
    {
        $this->_zone = $zone;

        return $this;
    }

    public function getZone()
    {
        return $this->_zone;
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

    private function _buildElements($form, $attributes, $deep, $parents = array())
    {
        if (!$this->_itemId) {
            throw new Exception('item_id not set');
        }

        if (!$this->_itemType) {
            throw new Exception('$this->_itemType not set');
        }

        foreach ($attributes as $attribute) {
            $subAttributes = $this->_service->getAttributes(array(
                'parent' => $attribute['id'],
                'zone'   => $this->_zone->id
            ));

            $nodeName = 'attr_' . $attribute['id'];
            if (count($subAttributes)) {
                $subform = new Zend_Form_SubForm(array(
                    'legend'                       => $attribute['name'],
                    'description'                  => $attribute['description'],
                    'disableLoadDefaultDecorators' => true,
                    //'elementsBelongTo'             => null,
                    'decorators'                   => array(
                        'FormElements',
                        array('viewScript', array(
                            'viewScript' => 'forms/specs-sub.phtml',
                            'placement'  => false,
                            'attribute'  => $attribute,
                            'deep'       => $deep,
                            'classes'    => $parents
                        )),
                    )
                ));
                $form->addSubForm($subform, $nodeName);
                $this->_buildElements($subform, $subAttributes, $deep + 1,
                    array_merge($parents, array('subform-' . $attribute['id'])));
            } else {

                $options = $this->_getFormElementOptions($attribute, $deep, $parents);

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

    private function _getFormElementOptions($attribute, $deep, $parents)
    {
        if (!$this->_itemId) {
            throw new Exception('item_id not set');
        }

        if (!$this->_itemType) {
            throw new Exception('$this->_itemType not set');
        }

        $value = isset($this->_actualValues[$attribute['id']]) ? $this->_actualValues[$attribute['id']] : null;

        $unit = null;
        if (!is_null($value)) {
            $unit = $this->_service->getUnit($attribute['unitId']);
        }

        $allValues = array();
        if (isset($this->_allValues[$attribute['id']])) {
            $allValues = $this->_allValues[$attribute['id']];
        }

        $options = array(
            'required'                     => false,
            'label'                        => $attribute['name'],
            'disableLoadDefaultDecorators' => true,
            'description'                  => $attribute['description'],
            'class'                        => 'input-sm form-control',
            'decorators'                   => array(
                'ViewHelper',
                array('viewScript', array(
                    'viewScript' => 'forms/specs-element.phtml',
                    'placement'  => false,
                    'deep'       => $deep,
                    'classes'    => $parents,
                    'actual'     => $value,
                    'unit'       => $unit,
                    'allValues'  => $allValues
                ))
            )
        );

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
                        array('Int', false, array('en_US'))
                    );
                    break;

                case 3: // float
                    $options['filters'] = array('StringTrim');
                    $options['validators'] = array(
                        array('Float', false, array('en_US'))
                    );
                    break;

                case 4: // textarea
                    $options['filters'] = array('StringTrim');
                    break;

                case 5: // checkbox
                    $options['multioptions'] = array(
                        ''  => '—',
                        '0' => 'нет',
                        '1' => 'да'
                    );
                    break;

                case 6: // select
                case 7: // treeselect
                    $multioptions = array(
                        ''  => '—'
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
        $this->_buildElements($this, $attributes, 0);
    }
}