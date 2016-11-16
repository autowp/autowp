<?php

namespace Application\Form\Element;

use Zend\Form\Element\Text;
use Zend\InputFilter\InputProviderInterface;

use Application\Model\Brand as BrandModel;

use Autowp\ZFComponents\Filter\SingleSpaces;

class BrandFullName extends Text implements InputProviderInterface
{
    protected $attributes = [
        'type'      => 'text',
        'maxlength' => BrandModel::MAX_FULLNAME,
        'size'      => BrandModel::MAX_FULLNAME
    ];

    /**
     * @var null|string
     */
    protected $label = 'moder/brands/meta-data/full-name';

    /**
     * Provide default input rules for this element
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
                        'max' => BrandModel::MAX_FULLNAME
                    ]
                ]
            ]
        ];
    }
}
