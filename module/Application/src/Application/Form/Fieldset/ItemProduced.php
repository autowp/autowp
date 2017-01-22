<?php

namespace Application\Form\Fieldset;

use Zend\Form\Fieldset;
use Zend\InputFilter\InputFilterProviderInterface;

class ItemProduced extends Fieldset implements InputFilterProviderInterface
{
    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);

        $elements = [
            [
                'name'    => 'count',
                'type'    => 'Number',
                'options' => [
                    'label'   => 'moder/item/produced/number'
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
                    'label'   => 'moder/item/produced/precision',
                    'options' => [
                        '0' => 'moder/item/produced/about',
                        '1' => 'moder/item/produced/exactly'
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
