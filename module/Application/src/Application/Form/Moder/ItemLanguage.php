<?php

namespace Application\Form\Moder;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;
use Autowp\ZFComponents\Filter\SingleSpaces;

class ItemLanguage extends Form implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('product');

        $elements = [
            [
                'name' => 'name',
                'type' => 'Text',
                'options' => [
                    'label' => 'moder/vehicle/name',
                ],
                'attributes' => [
                    'maxlength'  => 255,
                ],
            ],
            [
                'name' => 'text',
                'type' => 'Textarea',
                'options' => [
                    'label' => 'moder/item/short-description',
                ],
                'attributes' => [
                    'maxlength'  => 65536,
                    'rows'       => 5
                ],
            ],
            [
                'name' => 'full_text',
                'type' => 'Textarea',
                'options' => [
                    'label' => 'moder/item/full-description',
                ],
                'attributes' => [
                    'maxlength'  => 65536,
                    'rows'       => 10
                ],
            ]
        ];

        foreach ($elements as $element) {
            $this->add($element);
        }
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
            'name' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ]
            ],
            'text' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => 65536
                        ]
                    ]
                ]
            ],
            'full_text' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => 65536
                        ]
                    ]
                ]
            ]
        ];
    }
}
