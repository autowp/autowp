<?php

namespace Application\Form\Moder;

use Autowp\ZFComponents\Filter\SingleSpaces;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;

class ItemLanguage extends Form implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('product');

        $elements = [
            [
                'name'       => 'name',
                'type'       => 'Text',
                'options'    => [
                    'label' => 'moder/vehicle/name',
                ],
                'attributes' => [
                    'maxlength' => 255,
                ],
            ],
            [
                'name'       => 'text',
                'type'       => 'Textarea',
                'options'    => [
                    'label' => 'moder/item/short-description',
                ],
                'attributes' => [
                    'maxlength' => 65536,
                    'rows'      => 5,
                ],
            ],
            [
                'name'       => 'full_text',
                'type'       => 'Textarea',
                'options'    => [
                    'label' => 'moder/item/full-description',
                ],
                'attributes' => [
                    'maxlength' => 65536,
                    'rows'      => 10,
                ],
            ],
        ];

        foreach ($elements as $element) {
            $this->add($element);
        }
    }

    /**
     * Should return an array specification compatible with
     * {@link Laminas\InputFilter\Factory::createInputFilter()}.
     */
    public function getInputFilterSpecification(): array
    {
        return [
            'name'      => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class],
                ],
            ],
            'text'      => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class],
                ],
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'max' => 65536,
                        ],
                    ],
                ],
            ],
            'full_text' => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class],
                ],
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'max' => 65536,
                        ],
                    ],
                ],
            ],
        ];
    }
}
