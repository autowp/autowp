<?php

namespace Application\Form\Fieldset;

use Zend\Form\Fieldset;
use Zend\InputFilter\InputFilterProviderInterface;

class ItemBegin extends Fieldset implements InputFilterProviderInterface
{
    private $language = 'en';

    /**
     * @param  array|Traversable $options
     * @return Element|ElementInterface
     * @throws Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        parent::setOptions($options);

        if (isset($options['language'])) {
            $this->language = $options['language'];

            $this->get('month')->setOptions([
                'language' => $this->language
            ]);
        }

        return $this;
    }

    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);

        $elements = [
            [
                'name'    => 'year',
                'type'    => \Application\Form\Element\Year::class,
                'options' => [
                    'label' => 'moder/vehicle/year'
                ],
                'attributes' => [
                    'placeholder' => 'moder/vehicle/year',
                    'style'       => 'width: 10%',
                    'min'         => 1800,
                    'max'         => date('Y') + 10
                ]
            ],
            [
                'name'    => 'month',
                'type'    => \Application\Form\Element\Month::class,
                'options' => [
                    'label'    => 'moder/vehicle/month',
                    'language' => $this->language
                ],
                'attributes' => [
                    'title' => 'moder/vehicle/month',
                    'style' => 'width: 20%'
                ]
            ],
        ];

        foreach ($elements as $element) {
            $this->add($element);
        }

        $this->setAttribute('class', 'form-inline');
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
            'year' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'month' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
        ];
    }
}
