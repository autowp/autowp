<?php

namespace Application\Form\Element;

use Zend\Form\Element\Text;
use Zend\InputFilter\InputProviderInterface;

use Application\Model\Brand as BrandModel;

use Autowp\ZFComponents\Filter\SingleSpaces;

class BrandName extends Text implements InputProviderInterface
{
    protected $attributes = [
        'type'      => 'text',
        'maxlength' => BrandModel::MAX_NAME,
        'size'      => BrandModel::MAX_NAME
    ];

    /**
     * @var null|string
     */
    protected $label = 'brand/name';

    /**
     * Provide default input rules for this element
     *
     * @return array
     */
    public function getInputSpecification()
    {
        return [
            'name'     => $this->getName(),
            'required' => true,
            'filters'  => [
                ['name' => 'StringTrim'],
                ['name' => SingleSpaces::class]
            ],
            'validators' => [
                [
                    'name' => 'StringLength',
                    'options' => [
                        'max' => BrandModel::MAX_NAME
                    ]
                ]
            ]
        ];
    }
}
