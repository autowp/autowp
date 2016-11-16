<?php

namespace Application\Form\Element;

use Zend\Form\Element\Text;
use Zend\InputFilter\InputProviderInterface;

use Autowp\ZFComponents\Filter\SingleSpaces;

class CarName extends Text implements InputProviderInterface
{
    protected $attributes = [
        'type'      => 'text',
        'maxlength' => 80,
        'size'      => 80
    ];

    /**
     * @var null|string
     */
    protected $label = 'moder/vehicle/name';

    /**
     * Provide default input rules for this element
     *
     * Attaches a phone number validator.
     *
     * @return array
     */
    public function getInputSpecification()
    {
        return [
            'name' => $this->getName(),
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
                ['name' => SingleSpaces::class]
            ],
            'validators' => [
                [
                    'name' => 'StringLength',
                    'options' => [
                        'min' => 2,
                        'max' => 80
                    ]
                ]
            ]
        ];
    }
}
