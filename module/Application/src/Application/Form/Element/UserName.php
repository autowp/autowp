<?php

namespace Application\Form\Element;

use Zend\Form\Element\Text;
use Zend\InputFilter\InputProviderInterface;

use Application\Filter\SingleSpaces;
use Users;

class UserName extends Text implements InputProviderInterface
{
    protected $attributes = [
        'type'      => 'text',
        'maxlength' => Users::MAX_NAME,
        'size'      => Users::MAX_NAME
    ];

    /**
     * @var null|string
     */
    protected $label = 'user/name';

    /**
     * Provide default input rules for this element
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
                        'min' => Users::MIN_NAME,
                        'max' => Users::MAX_NAME
                    ]
                ]
            ]
        ];
    }
}