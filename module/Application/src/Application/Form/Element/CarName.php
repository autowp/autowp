<?php

namespace Application\Form\Element;

use Zend\Form\Element\Text;
use Zend\InputFilter\InputProviderInterface;

use Autowp\ZFComponents\Filter\SingleSpaces;

use Application\Model\DbTable;

class CarName extends Text implements InputProviderInterface
{
    protected $attributes = [
        'type'      => 'text',
        'maxlength' => DbTable\Vehicle::MAX_NAME,
        'size'      => DbTable\Vehicle::MAX_NAME
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
                        'max' => DbTable\Vehicle::MAX_NAME
                    ]
                ]
            ]
        ];
    }
}
