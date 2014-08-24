<?php

class Application_Form_Moder_Car_New extends Project_Form
{
    protected $_inheritedCarType = null;

    protected $_inheritedIsConcept = null;

    /**
     * @var Car_Types
     */
    protected $_carTypeTable = null;

    public function setInheritedCarType($value)
    {
        $this->_inheritedCarType = $value;

        return $this;
    }

    public function setInheritedIsConcept($value)
    {
        $this->_inheritedIsConcept = $value === null ? null : (bool)$value;

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

            $carTypeOptions = array(
                'inherited' => 'inherited (' . $carTypeName . ')'
            ) + $carTypeOptions;
        } else {
            $carTypeOptions = array(
                'inherited' => 'inherited'
            ) + $carTypeOptions;
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
            'legend'      => 'Новая машина',
            'elements'    => array(
                array('Car_Caption', 'caption', array(
                    'required'   => true,
                    'id'         => 'car_caption',
                    'validators' => array('Car_NameNotExists'),
                    'decorators' => array('ViewHelper')
                )),
                array('Car_Body', 'body', array(
                    'required'     => false,
                    'order'        => 2,
                    'decorators'   => array('ViewHelper'),
                    'style'        => 'width: 30%'
                )),
                array('select', 'car_type_id', array(
                    'label'        => 'Тип кузова',
                    'required'     => false,
                    'order'        => 3,
                    'decorators'   => array('ViewHelper'),
                    'multioptions' => $carTypeOptions,
                    'style'        => 'width: 30%'
                )),
                array('year', 'begin_model_year', array(
                    'required'     => false,
                    'label'        => 'с',
                    'placeholder'  => 'с',
                    'decorators'   => array('ViewHelper'),
                )),
                array('year', 'end_model_year', array(
                    'required'     => false,
                    'label'        => 'по',
                    'placeholder'  => 'по',
                    'decorators'   => array('ViewHelper'),
                )),
                array('year', 'begin_year', array(
                    'required'     => false,
                    'label'        => 'год',
                    'decorators'   => array('ViewHelper'),
                    'placeholder'  => 'год',
                    'style'        => 'width: 10%'
                )),
                array('month', 'begin_month', array(
                    'required'     => false,
                    'label'        => 'месяц',
                    'decorators'   => array('ViewHelper'),
                    'style'        => 'width: 20%'
                )),
                array('year', 'end_year', array(
                    'required'     => false,
                    'label'        => 'год',
                    'decorators'   => array('ViewHelper'),
                    'placeholder'  => 'год',
                    'style'        => 'width: 10%'
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
                )),
                array('select', 'is_concept', array(
                    'required'     => false,
                    'label'        => 'Концепт (прототип)',
                    'order'        => 9,
                    'multioptions' => $isConceptOptions,
                    'decorators'   => array('ViewHelper'),
                    'style'        => 'width: 20%'
                )),
                array('checkbox', 'is_group', array(
                    'required'     => false,
                    'label'        => 'Группа',
                    'decorators'   => array('ViewHelper'),
                    'order'        => 10,
                )),
            ),
            'displayGroups'=> array(
                'model_years' =>    array(
                    'elements' => array('begin_model_year', 'end_model_year'),
                    'options'  => array(
                        'legend'     => 'Модельный год',
                        'order'      => 4,
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
                        'order'      => 6,
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
                        'order'  => 7,
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