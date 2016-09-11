<?php

namespace Application\Form\Fieldset;

use Zend\Form\Fieldset;
use Zend\InputFilter\InputFilterProviderInterface;

class CarProduced extends Fieldset implements InputFilterProviderInterface
{
    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);

        $elements = [
            [
                'name'    => 'count',
                'type'    => 'Number',
                'options' => [
                    'label'   => 'единиц'
                ],
                'attributes' => [
                    'style' => 'width: 10%',
                    'min'   => 0,
                    'max'   => 100000000
                ]
            ],
            [
                'name'    => 'exactly',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'точно?',
                    'options' => [
                        '0' => 'примерно',
                        '1' => 'точно'
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
            'count' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'exactly' => [
                'required' => false
            ],
        ];
    }
}