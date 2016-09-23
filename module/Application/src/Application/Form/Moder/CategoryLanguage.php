<?php

namespace Application\Form\Moder;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

use Application\Filter\SingleSpaces;

class CategoryLanguage extends Form implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('product');

        $elements = [
            [
                'name' => 'name',
                'type' => 'Text',
                'options' => [
                    'label' => 'Название',
                ],
                'attributes' => [
                    'maxlength'  => 255,
                ],
            ],
            [
                'name' => 'short_name',
                'type' => 'Text',
                'options' => [
                    'label' => 'Short name',
                ],
                'attributes' => [
                    'maxlength'  => 255,
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
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ]
            ],
            'short_name' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ]
            ]
        ];
    }
}