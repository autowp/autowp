<?php

class Application_Form_Moder_Car_Edit_Meta extends Project_Form
{
    public function init()
    {
        parent::init();

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
                    'class'        => 'span5'
                )),
                array('Car_Body', 'body', array(
                    'required'     => false,
                    'order'        => 2,
                    'decorators'   => array('ViewHelper'),
                    'class'        => 'span2'
                )),
                array('Car_Type', 'car_type_id', array(
                    'required'     => false,
                    'order'        => 3,
                    'decorators'   => array('ViewHelper'),
                    'class'        => 'span3'
                )),
                array('year', 'begin_model_year', array(
                    'required'     => false,
                    'label'        => 'с',
                    'placeholder'  => 'с',
                    'decorators'   => array('ViewHelper'),
                    'class'        => 'span1'
                )),
                array('year', 'end_model_year', array(
                    'required'     => false,
                    'label'        => 'по',
                    'placeholder'  => 'по',
                    'decorators'   => array('ViewHelper'),
                    'class'        => 'span1'
                )),
                array('year', 'begin_year', array(
                    'required'     => false,
                    'label'        => 'год',
                    'decorators'   => array('ViewHelper'),
                    'class'        => 'span1'
                )),
                array('month', 'begin_month', array(
                    'required'     => false,
                    'label'        => 'месяц',
                    'decorators'   => array('ViewHelper'),
                    'class'        => 'span2'
                )),
                array('year', 'end_year', array(
                    'required'     => false,
                    'label'        => 'год',
                    'decorators'   => array('ViewHelper'),
                    'class'        => 'span1'
                )),
                array('month', 'end_month', array(
                    'required'     => false,
                    'label'        => 'месяц',
                    'decorators'   => array('ViewHelper'),
                    'class'        => 'span2'
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
                    'class'        => 'span2'
                )),
                array('uint', 'produced', array(
                    'required'     => false,
                    'label'        => 'единиц',
                    'decorators'   => array('ViewHelper'),
                    'class'        => 'span1'
                )),
                array('select', 'produced_exactly', array(
                    'required'     => false,
                    'label'        => 'точно?',
                    'multioptions' => array(
                        '0' => 'примерно',
                        '1' => 'точно'
                    ),
                    'decorators'   => array('ViewHelper'),
                    'class'        => 'span2'
                )),
                array('checkbox', 'is_concept', array(
                    'required'     => false,
                    'label'        => 'Концепт (прототип)',
                    'order'        => 9,
                    'decorators'   => array('ViewHelper')
                )),
                array('description', 'description', array(
                    'required'     => false,
                    'label'        => 'Краткое описание',
                    'order'        => 11,
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
                        'order'      => 4,
                        'decorators' => array(
                            'FormElements',
                            array('HtmlTag', array('tag' => 'div', 'class' => 'controls-row'))
                        )
                    )
                ),
                'begin_group' =>    array(
                    'elements' => array('begin_year', 'begin_month'),
                    'options'  => array(
                        'legend'     => 'Выпускалась с',
                        'order'      => 6,
                        'decorators' => array(
                            'FormElements',
                            array('HtmlTag', array('tag' => 'div', 'class' => 'controls-row'))
                        )
                    )
                ),
                'end_group'      => array(
                    'elements' => array('end_year', 'end_month', 'today'),
                    'options'  => array(
                        'legend' => 'Выпускалась по',
                        'order'  => 7,
                        'decorators' => array(
                            'FormElements',
                            array('HtmlTag', array('tag' => 'div', 'class' => 'controls-row'))
                        )
                    )
                ),
                'produced_group' => array(
                    'elements' => array('produced', 'produced_exactly'),
                    'options'  => array(
                        'legend' => 'Выпущено единиц',
                        'order'  => 8,
                        'decorators' => array(
                            'FormElements',
                            array('HtmlTag', array('tag' => 'div', 'class' => 'controls-row'))
                        )
                    )
                ),
            )
        ));
    }
}