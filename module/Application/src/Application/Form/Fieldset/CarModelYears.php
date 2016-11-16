<?php

namespace Application\Form\Fieldset;

use Zend\Form\Fieldset;
use Zend\InputFilter\InputFilterProviderInterface;

class CarModelYears extends Fieldset implements InputFilterProviderInterface
{
    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);

        $elements = [
            [
                'name'    => 'begin',
                'type'    => \Application\Form\Element\Year::class,
                'options' => [
                    'label' => 'moder/vehicle/year/from'
                ],
                'attributes' => [
                    'placeholder' => 'moder/vehicle/year/from',
                    'style'       => 'width: 10%',
                    'min'         => 1800,
                    'max'         => date('Y') + 10
                ]
            ],
            [
                'name'    => 'end',
                'type'    => \Application\Form\Element\Year::class,
                'options' => [
                    'label' => 'moder/vehicle/year/to'
                ],
                'attributes' => [
                    'placeholder' => 'moder/vehicle/year/to',
                    'style'       => 'width: 10%',
                    'min'         => 1800,
                    'max'         => date('Y') + 10
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
            'begin' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'end' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ]
        ];
    }
}
