<?php

namespace Application\Form\Element;

use Zend\Form\Element\Text;
use Zend\InputFilter\InputProviderInterface;

use Application\Filter\SingleSpaces;
use Application\Model\Brand;

class BrandFullName extends Text implements InputProviderInterface
{
    protected $attributes = [
        'type'      => 'text',
        'maxlength' => Brand::MAX_FULLNAME,
        'size'      => Brand::MAX_FULLNAME
    ];

    /**
     * @var null|string
     */
    protected $label = 'Полное название';

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
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
                ['name' => SingleSpaces::class]
            ],
            'validators' => [
                [
                    'name' => 'StringLength',
                    'options' => [
                        'max' => Brand::MAX_NAME
                    ]
                ]
            ]
        ];
    }
}