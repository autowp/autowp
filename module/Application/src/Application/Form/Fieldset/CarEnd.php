<?php

namespace Application\Form\Fieldset;

use Zend\Form\Fieldset;
use Zend\InputFilter\InputFilterProviderInterface;

class CarEnd extends Fieldset implements InputFilterProviderInterface
{
    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);

        $elements = [
            [
                'name'    => 'year',
                'type'    => \Application\Form\Element\Year::class,
                'options' => [
                    'label' => 'год'
                ],
                'attributes' => [
                    'placeholder' => 'год',
                    'style'       => 'width: 10%',
                    'min'         => 1800,
                    'max'         => date('Y') + 10
                ]
            ],
            [
                'name'    => 'month',
                'type'    => \Application\Form\Element\Month::class,
                'options' => [
                    'label' => 'месяц'
                ],
                'attributes' => [
                    'placeholder'  => 'месяц',
                    'style'        => 'width: 20%'
                ]
            ],
            [
                'name'    => 'today',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'наше время',
                    'options' => [
                        ''  => '--',
                        '0' => 'выпуск закончен',
                        '1' => 'производится в н.в.'
                    ]
                ],
                'attributes' => [
                    'style' => 'width: 20%'
                ]
            ],
        ];

        foreach ($elements as $element) {
            $this->add($element);
        }

        $this->setAttribute('class', 'form-inline');
    }

    /**
     * Should return an array specification compatible with
     * {@link Zend\InputFilter\Factory::createInputFilter()}.
     *
     * @return array
     */
    public function getInputFilterSpecification()
    {
        return [
            'year' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'month' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'today' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
        ];
    }
}