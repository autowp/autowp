<?php

namespace Application\Form\Element;

use Zend\Form\Element\Text;
use Zend\InputFilter\InputProviderInterface;

use Application\Filter\SingleSpaces;
use Application\Model\DbTable\Engine;

class EngineName extends Text implements InputProviderInterface
{
    protected $attributes = [
        'type'      => 'text',
        'maxlength' => Engine::MAX_NAME,
        'size'      => Engine::MAX_NAME
    ];

    /**
     * @var null|string
     */
    protected $label = 'engine/name';

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
                        'max' => Engine::MAX_NAME
                    ]
                ]
            ]
        ];
    }
}