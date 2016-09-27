<?php

namespace Application\Form\Element;

use Zend\Form\Element\Password;
use Zend\InputFilter\InputProviderInterface;

use Users;

class UserPassword extends Password implements InputProviderInterface
{
    protected $attributes = [
        'type'      => 'password',
        'size'      => Users::MAX_PASSWORD,
        'maxlength' => Users::MAX_PASSWORD
    ];

    /**
     * @var null|string
     */
    protected $label = 'user/password';

    /**
     * Provide default input rules for this element
     *
     * @return array
     */
    public function getInputSpecification()
    {
        return [
            'name'       => $this->getName(),
            'required'   => true,
            'validators' => [
                [
                    'name' => 'StringLength',
                    'options' => [
                        'max' => Users::MAX_PASSWORD
                    ]
                ]
            ]
        ];
    }
}