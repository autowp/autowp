<?php

namespace Application\Form;

use Project_Form;

class Modification extends Project_Form
{
    private $_groupOptions = [];

    public function setGroupOptions(array $options)
    {
        $this->_groupOptions = $options;

        return $this;
    }

    public function init()
    {
        parent::init();

         $this->setOptions([
            'method'     => 'post',
            'decorators' => [
                'PrepareElements',
                ['viewScript', [
                    'viewScript' => 'forms/bootstrap-horizontal.phtml'
                ]],
                'Form'
            ],
            'elements' => [
                ['select', 'group_id', [
                    'label'        => 'Группа',
                    'required'     => false,
                    'decorators'   => ['ViewHelper'],
                    'multioptions' => $this->_groupOptions,
                ]],
                ['text', 'name', [
                    'label'       => 'Название',
                    'required'    => true,
                    'filters'     => ['StringTrim'],
                    'maxlength'   => 255,
                    'size'        => 80,
                    'decorators'  => ['ViewHelper'],
                ]],
                ['year', 'begin_model_year', [
                    'required'     => false,
                    'label'        => 'с',
                    'placeholder'  => 'с',
                    'decorators'   => ['ViewHelper'],
                    'style'        => 'width: 10%',
                    'min'          => 1800,
                ]],
                ['year', 'end_model_year', [
                    'required'     => false,
                    'label'        => 'по',
                    'placeholder'  => 'по',
                    'decorators'   => ['ViewHelper'],
                    'style'        => 'width: 10%',
                    'min'          => 1800,
                ]],
                ['year', 'begin_year', [
                    'required'     => false,
                    'label'        => 'год',
                    'decorators'   => ['ViewHelper'],
                    'placeholder'  => 'год',
                    'style'        => 'width: 10%',
                    'min'          => 1800,
                ]],
                ['month', 'begin_month', [
                    'required'     => false,
                    'label'        => 'месяц',
                    'decorators'   => ['ViewHelper'],
                    'style'        => 'width: 20%',
                ]],
                ['year', 'end_year', [
                    'required'     => false,
                    'label'        => 'год',
                    'decorators'   => ['ViewHelper'],
                    'placeholder'  => 'год',
                    'style'        => 'width: 10%',
                    'min'          => 1800,
                ]],
                ['month', 'end_month', [
                    'required'     => false,
                    'label'        => 'месяц',
                    'decorators'   => ['ViewHelper'],
                    'style'        => 'width: 20%'
                ]],
                ['select', 'today', [
                    'required'     => false,
                    'label'        => 'наше время',
                    'multioptions' => [
                        '0' => '--',
                        '1' => 'выпуск закончен',
                        '2' => 'производится в н.в.'
                    ],
                    'decorators'   => ['ViewHelper'],
                    'style'        => 'width: 20%'
                ]],
                ['uint', 'produced', [
                    'required'     => false,
                    'label'        => 'единиц',
                    'decorators'   => ['ViewHelper'],
                    'style'        => 'width: 10%',
                    'min'          => 0,
                    'max'          => 100000000
                ]],
                ['select', 'produced_exactly', [
                    'required'     => false,
                    'label'        => 'точно?',
                    'multioptions' => [
                        '0' => 'примерно',
                        '1' => 'точно'
                    ],
                    'decorators'   => ['ViewHelper'],
                    'style'        => 'width: 20%'
                ]]
            ],
             'displayGroups'=> [
                 'model_years' =>    [
                     'elements' => ['begin_model_year', 'end_model_year'],
                     'options'  => [
                         'legend'     => 'Модельный год',
                         'order'      => 5,
                         'decorators'  => [
                             ['viewScript', [
                                 'viewScript' => 'forms/bootstrap-group-inline.phtml'
                             ]],
                         ],
                     ]
                 ],
                 'begin_group' =>    [
                     'elements' => ['begin_year', 'begin_month'],
                     'options'  => [
                         'legend'     => 'Выпускалась с',
                         'order'      => 7,
                         'decorators'  => [
                             ['viewScript', [
                                 'viewScript' => 'forms/bootstrap-group-inline.phtml'
                             ]],
                         ],
                     ]
                 ],
                 'end_group'      => [
                     'elements' => ['end_year', 'end_month', 'today'],
                     'options'  => [
                         'legend' => 'Выпускалась по',
                         'order'  => 8,
                         'decorators'  => [
                             ['viewScript', [
                                 'viewScript' => 'forms/bootstrap-group-inline.phtml'
                             ]],
                         ]
                     ]
                 ],
                 'produced_group' => [
                     'elements' => ['produced', 'produced_exactly'],
                     'options'  => [
                         'legend' => 'Выпущено единиц',
                         'order'  => 9,
                         'decorators'  => [
                             ['viewScript', [
                                 'viewScript' => 'forms/bootstrap-group-inline.phtml'
                             ]],
                         ],
                     ]
                 ],
             ]
        ]);
    }
}