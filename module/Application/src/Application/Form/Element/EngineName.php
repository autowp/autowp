<?php

namespace Application\Form\Element;

use Zend\Form\Element\Text;
use Zend\InputFilter\InputProviderInterface;

use Application\Filter\SingleSpaces;
use Engines;

class EngineName extends Text implements InputProviderInterface
{
    protected $attributes = [
        'type'      => 'text',
        'maxlength' => Engines::MAX_NAME,
        'size'      => Engines::MAX_NAME
    ];

    /**
     * @var null|string
     */
    protected $label = 'Название';

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
                        'max' => Engines::MAX_NAME
                    ]
                ]
            ]
        ];
    }
}