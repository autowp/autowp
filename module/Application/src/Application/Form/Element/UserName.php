<?php

namespace Application\Form\Element;

use Zend\Form\Element\Text;
use Zend\InputFilter\InputProviderInterface;

use Autowp\ZFComponents\Filter\SingleSpaces;
use Application\Model\DbTable\User;

class UserName extends Text implements InputProviderInterface
{
    protected $attributes = [
        'type'      => 'text',
        'maxlength' => User::MAX_NAME,
        'size'      => User::MAX_NAME
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
                        'min' => User::MIN_NAME,
                        'max' => User::MAX_NAME
                    ]
                ]
            ]
        ];
    }
}
