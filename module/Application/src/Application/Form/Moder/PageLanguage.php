<?php

namespace Application\Form\Moder;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

use Application\Filter\SingleSpaces;

class PageLanguage extends Form implements InputFilterProviderInterface
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
                'name' => 'title',
                'type' => 'Text',
                'options' => [
                    'label' => 'Title',
                ],
                'attributes' => [
                    'maxlength'  => 255,
                ],
            ],
            [
                'name' => 'breadcrumbs',
                'type' => 'Text',
                'options' => [
                    'label' => 'Breadcrumbs',
                ],
                'attributes' => [
                    'maxlength'  => 100,
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
            'title' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ]
            ],
            'breadcrumbs' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ]
            ]
        ];
    }
}