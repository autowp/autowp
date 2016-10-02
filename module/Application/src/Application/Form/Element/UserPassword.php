<?php

namespace Application\Form\Element;

use Zend\Form\Element\Password;
use Zend\InputFilter\InputProviderInterface;

use Application\Model\DbTable\User;

class UserPassword extends Password implements InputProviderInterface
{
    protected $attributes = [
        'type'      => 'password',
        'size'      => User::MAX_PASSWORD,
        'maxlength' => User::MAX_PASSWORD
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
                        'min' => User::MIN_PASSWORD,
                        'max' => User::MAX_PASSWORD
                    ]
                ]
            ]
        ];
    }
}