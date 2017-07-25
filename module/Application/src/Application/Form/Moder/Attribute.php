<?php

namespace Application\Form\Moder;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

use Application\Model\DbTable\Attr;

use Autowp\ZFComponents\Filter\SingleSpaces;

class Attribute extends Form implements InputFilterProviderInterface
{
    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);

        $elements = [
            [
                'name'    => 'name',
                'type'    => 'Text',
                'options' => [
                    'label' => 'attrs/attribute/name'
                ]
            ],
            [
                'name'    => 'type_id',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'attrs/attribute/type',
                    'options' => ['' => '--']
                ]
            ],
            [
                'name'    => 'precision',
                'type'    => 'Text',
                'options' => [
                    'label'     => 'attrs/attribute/precision',
                    'maxlength' => 255,
                    'size'      => 80
                ]
            ],
            [
                'name'    => 'unit_id',
                'type'    => 'Select',
                'options' => [
                    'label'     => 'attrs/attribute/unit',
                    'options'   => ['' => '--']
                ]
            ],
            [
                'name'    => 'description',
                'type'    => 'Textarea',
                'options' => [
                    'label'    => 'attrs/attribute/description',
                    'rows'     => 3,
                    'cols'     => 30,
                ]
            ]
        ];

        foreach ($elements as $element) {
            $this->add($element);
        }

        $this->setAttribute('method', 'post');
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
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ]
            ],
            'type_id' => [
                'required' => true,
            ],
            'precision' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'unit_id' => [
                'required' => false
            ],
            'description' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ]
            ]
        ];
    }
}
