<?php

namespace Application\Form\Moder;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

use Autowp\ZFComponents\Filter\SingleSpaces;

class AttributeListOption extends Form implements InputFilterProviderInterface
{
    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);

        $elements = [
            [
                'name'    => 'parent_id',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'attrs/list-options/parent',
                    'options' => ['' => '--']
                ]
            ],
            [
                'name'    => 'name',
                'type'    => 'Text',
                'options' => [
                    'label' => 'attrs/list-options/name'
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
            'parent_id' => [
                'required' => false,
            ],
            'name' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ]
            ]
        ];
    }
}
