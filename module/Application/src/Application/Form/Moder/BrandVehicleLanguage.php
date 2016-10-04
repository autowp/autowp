<?php

namespace Application\Form\Moder;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

use Application\Filter\SingleSpaces;
use Application\Model\DbTable;

class BrandVehicleLanguage extends Form implements InputFilterProviderInterface
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
        }

        return $this;
    }

    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);

        $elements = [
            [
                'name'    => 'name',
                'type'    => 'Text',
                'options' => [
                    'label' => 'Name'
                ],
                'attributes' => [
                    'maxlength' => DbTable\Brand\VehicleLanguage::MAX_NAME
                ]
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
                'filters'  => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => 0,
                            'max' => DbTable\Brand\VehicleLanguage::MAX_NAME
                        ]
                    ]
                ]
            ]
        ];
    }
}