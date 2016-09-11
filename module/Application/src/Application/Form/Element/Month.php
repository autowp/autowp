<?php

namespace Application\Form\Element;

use Zend\Form\Element\Select;
use Zend\InputFilter\InputProviderInterface;

use Zend_Date;

class Month extends Select implements InputProviderInterface
{
    protected $attributes = [
        'type' => 'select'
    ];

    /**
     * @var null|string
     */
    protected $label = 'Месяц';

    /**
     * @param  null|int|string  $name    Optional name for the element
     * @param  array            $options Optional options for the element
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($name = null, $options = [])
    {
        $multioptions = [
            ''  =>  '--'
        ];

        $date = new Zend_Date([
            'year'  =>  2000,
            'month' =>  1,
            'day'   =>  1
        ]);
        for ($i=1; $i<=12; $i++) {
            $multioptions[$i] = sprintf('%02d - ', $i) . $date->setMonth($i)->toString('MMMM');
        }

        $options['options'] = $multioptions;

        parent::__construct($name, $options);
    }

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
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim']
            ],
            'validators' => [
                ['name' => 'Digits'],
                [
                    'name'    => 'Between',
                    'options' => [
                        'min'       => 1,
                        'max'       => 12,
                        'inclusive' => true
                    ]
                ]
            ]
        ];
    }
}