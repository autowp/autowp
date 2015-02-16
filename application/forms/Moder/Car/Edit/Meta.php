<?php

class Application_Form_Moder_Car_Edit_Meta extends Project_Form
{
    protected $_isGroupDisabled = false;

    protected $_inheritedCarType = null;

    protected $_inheritedSpec = null;

    protected $_inheritedIsConcept = null;

    protected $_specOptions = array();

    /**
     * @var Car_Types
     */
    protected $_carTypeTable = null;

    public function setInheritedCarType($value)
    {
        $this->_inheritedCarType = $value;

        return $this;
    }

    public function setInheritedSpec($value)
    {
        $this->_inheritedSpec = $value;

        return $this;
    }

    public function setInheritedIsConcept($value)
    {
        $this->_inheritedIsConcept = $value === null ? null : (bool)$value;

        return $this;
    }

    protected function setIsGroupDisabled($value)
    {
        $this->_isGroupDisabled = (bool)$value;

        return $this;
    }

    public function setSpecOptions(array $options)
    {
        $this->_specOptions = $options;

        return $this;
    }

    /**
     * @return Car_Types
     */
    protected function _getCarTypeTable()
    {
        return $this->_carTypeTable
            ? $this->_carTypeTable
            : $this->_carTypeTable = new Car_Types();
    }

    protected function _getCarTypeOptions($parentId = null)
    {
        if ($parentId) {
            $filter = array(
                'parent_id = ?' => $parentId
            );
        } else {
            $filter = 'parent_id is null';
        }

        $rows = $this->_getCarTypeTable()->fetchAll($filter, 'position');
        $result = array();
        foreach ($rows as $row) {
            $result[$row->id] = $row->name;

            foreach ($this->_getCarTypeOptions($row->id) as $key => $value) {
                $result[$key] = '...' . $value;
            }
        }

        return $result;
    }

    public function init()
    {
        parent::init();

        $carTypeOptions = $this->_getCarTypeOptions();

        $carTypeOptions = array('' => '-') + $carTypeOptions;

        if (!is_null($this->_inheritedCarType)) {

            $carType = $this->_getCarTypeTable()->find($this->_inheritedCarType)->current();
            $carTypeName = $carType ? $carType->name : '-';

            $carTypeOptions = array('inherited' => 'inherited (' . $carTypeName . ')') + $carTypeOptions;
        } else {
            $carTypeOptions = array('inherited' => 'inherited') + $carTypeOptions;
        }

        if (!is_null($this->_inheritedSpec)) {
            $specOptions = array('inherited' => 'inherited (' . $this->_inheritedSpec . ')') + $this->_specOptions;
        } else {
            $specOptions = array('inherited' => 'inherited') + $this->_specOptions;
        }

        $isConceptOptions = array(
            '0' => 'нет',
            '1' => 'да',
        );
        if (!is_null($this->_inheritedIsConcept)) {
            $isConceptOptions = array_merge(array(
                'inherited' => 'inherited (' . ($this->_inheritedIsConcept ? 'да' : 'нет') . ')'
            ), $isConceptOptions);
        } else {
            $isConceptOptions = array_merge(array(
                'inherited' => 'inherited'
            ), $isConceptOptions);
        }

        $this->setOptions(array(
            'method'      => 'post',
            'decorators'  => array(
                'PrepareElements',
                array('viewScript', array(
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                )),
                'Form'
            ),
            'elements'    => array(
                array('Car_Caption', 'caption', array(
                    'required'     => true,
                    'order'        => 1,
                    'decorators'   => array('ViewHelper'),
                )),
                array('Car_Body', 'body', array(
                    'required'     => false,
                    'order'        => 2,
                    'decorators'   => array('ViewHelper'),
                    'style'        => 'width: 30%'
                )),
                array('select', 'spec_id', array(
                    'required'     => false,
                    'label'        => 'Spec',
                    'order'        => 3,
                    'multioptions' => $specOptions,
                    'decorators'   => array('ViewHelper'),
                    'style'        => 'width: 20%'
                )),
                array('select', 'car_type_id', array(
                    'label'        => 'Тип кузова',
                    'required'     => false,
                    'order'        => 4,
                    'decorators'   => array('ViewHelper'),
                    'multioptions' => $carTypeOptions,
                    'style'        => 'width: 30%'
                )),
                array('year', 'begin_model_year', array(
                    'required'     => false,
                    'label'        => 'с',
                    'placeholder'  => 'с',
                    'decorators'   => array('ViewHelper'),
                    'style'        => 'width: 10%',
                    'min'          => 1800,
                )),
                array('year', 'end_model_year', array(
                    'required'     => false,
                    'label'        => 'по',
                    'placeholder'  => 'по',
                    'decorators'   => array('ViewHelper'),
                    'style'        => 'width: 10%',
                    'min'          => 1800,
                )),
                array('year', 'begin_year', array(
                    'required'     => false,
                    'label'        => 'год',
                    'decorators'   => array('ViewHelper'),
                    'placeholder'  => 'год',
                    'style'        => 'width: 10%',
                    'min'          => 1800,
                )),
                array('month', 'begin_month', array(
                    'required'     => false,
                    'label'        => 'месяц',
                    'decorators'   => array('ViewHelper'),
                    'style'        => 'width: 20%',
                )),
                array('year', 'end_year', array(
                    'required'     => false,
                    'label'        => 'год',
                    'decorators'   => array('ViewHelper'),
                    'placeholder'  => 'год',
                    'style'        => 'width: 10%',
                    'min'          => 1800,
                )),
                array('month', 'end_month', array(
                    'required'     => false,
                    'label'        => 'месяц',
                    'decorators'   => array('ViewHelper'),
                    'style'        => 'width: 20%'
                )),
                array('select', 'today', array(
                    'required'     => false,
                    'label'        => 'наше время',
                    'multioptions' => array(
                        '0' => '--',
                        '1' => 'выпуск закончен',
                        '2' => 'производится в н.в.'
                    ),
                    'decorators'   => array('ViewHelper'),
                    'style'        => 'width: 20%'
                )),
                array('uint', 'produced', array(
                    'required'     => false,
                    'label'        => 'единиц',
                    'decorators'   => array('ViewHelper'),
                    'style'        => 'width: 10%',
                    'min'          => 0,
                    'max'          => 100000000
                )),
                array('select', 'produced_exactly', array(
                    'required'     => false,
                    'label'        => 'точно?',
                    'multioptions' => array(
                        '0' => 'примерно',
                        '1' => 'точно'
                    ),
                    'decorators'   => array('ViewHelper'),
                    'style'        => 'width: 20%'
                )),
                array('select', 'is_concept', array(
                    'required'     => false,
                    'label'        => 'Концепт (прототип)',
                    'order'        => 10,
                    'multioptions' => $isConceptOptions,
                    'decorators'   => array('ViewHelper'),
                    'style'        => 'width: 20%'
                )),
                array('checkbox', 'is_group', array(
                    'required'     => false,
                    'label'        => 'Группа',
                    'order'        => 11,
                    'decorators'   => array('ViewHelper'),
                    'disabled'     => $this->_isGroupDisabled ? true : null
                )),
                array('description', 'description', array(
                    'required'     => false,
                    'label'        => 'Краткое описание',
                    'order'        => 12,
                    'cols'         => 60,
                    'rows'         => 6,
                    'filters'      => array('StringTrim'),
                    'class'        => 'html',
                    'style'        => 'width:380px;height:200px;',
                    'decorators'   => array('ViewHelper')
                )),
            ),
            'displayGroups'=> array(
                'model_years' =>    array(
                    'elements' => array('begin_model_year', 'end_model_year'),
                    'options'  => array(
                        'legend'     => 'Модельный год',
                        'order'      => 5,
                        'decorators'  => array(
                            array('viewScript', array(
                                'viewScript' => 'forms/bootstrap-group-inline.phtml'
                            )),
                        ),
                    )
                ),
                'begin_group' =>    array(
                    'elements' => array('begin_year', 'begin_month'),
                    'options'  => array(
                        'legend'     => 'Выпускалась с',
                        'order'      => 7,
                        'decorators'  => array(
                            array('viewScript', array(
                                'viewScript' => 'forms/bootstrap-group-inline.phtml'
                            )),
                        ),
                    )
                ),
                'end_group'      => array(
                    'elements' => array('end_year', 'end_month', 'today'),
                    'options'  => array(
                        'legend' => 'Выпускалась по',
                        'order'  => 8,
                        'decorators'  => array(
                            array('viewScript', array(
                                'viewScript' => 'forms/bootstrap-group-inline.phtml'
                            )),
                        ),
                    )
                ),
                'produced_group' => array(
                    'elements' => array('produced', 'produced_exactly'),
                    'options'  => array(
                        'legend' => 'Выпущено единиц',
                        'order'  => 9,
                        'decorators'  => array(
                            array('viewScript', array(
                                'viewScript' => 'forms/bootstrap-group-inline.phtml'
                            )),
                        ),
                    )
                ),
            )
        ));
    }
}