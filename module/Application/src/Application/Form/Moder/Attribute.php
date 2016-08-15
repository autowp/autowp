<?php

namespace Application\Form\Moder;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

use Application\Filter\SingleSpaces;

use Attrs_Types;
use Attrs_Units;

class Attribute extends Form implements InputFilterProviderInterface
{
    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);
        
        $typesTable = new Attrs_Types();
        $db = $typesTable->getAdapter();
        $typeOptions = $db->fetchPairs(
            $db->select()
                ->from($typesTable->info('name'), ['id', 'name'])
        );
        
        $unitTable = new Attrs_Units();
        $db = $unitTable->getAdapter();
        $unitOptions = $db->fetchPairs(
            $db->select()
                ->from($unitTable->info('name'), ['id', 'name'])
        );
    
        $elements = [
            [
                'name'    => 'name',
                'type'    => 'Text',
                'options' => [
                    'label' => 'Название'
                ]
            ],
            [
                'name'    => 'type_id',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'Тип',
                    'options' => array_replace(['' => '--'], $typeOptions)
                ]
            ],
            [
                'name'    => 'precision',
                'type'    => 'Text',
                'options' => [
                    'label'     => 'Точность (для float аттрибута)',
                    'maxlength' => 255,
                    'size'      => 80
                ]
            ],
            [
                'name'    => 'unit_id',
                'type'    => 'Select',
                'options' => [
                    'label'     => 'Единица измерения',
                    'options'   => array_replace(['' => '--'], $unitOptions)
                ]
            ],
            [
                'name'    => 'description',
                'type'    => 'Textarea',
                'options' => [
                    'label'    => 'Описание',
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