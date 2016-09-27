<?php

namespace Application\Form\Element;

use Zend\Form\Element\Select;
use Zend\InputFilter\InputProviderInterface;

use DateTime;
use IntlDateFormatter;

class Month extends Select implements InputProviderInterface
{
    protected $attributes = [
        'type' => 'select'
    ];

    /**
     * @var null|string
     */
    protected $label = 'month';

    private $language = 'en';

    /**
     * @param  array|Traversable $options
     * @return Month|ElementInterface
     * @throws InvalidArgumentException
     */
    public function setOptions($options)
    {
        if (isset($options['language'])) {
            $this->language = $options['language'];
        }

        if (!isset($options['options']) && !isset($options['value_options'])) {

            $multioptions = [
                '' => '--'
            ];

            $dateFormatter = new IntlDateFormatter($this->language, IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, 'MM - MMMM');

            $date = new DateTime();
            for ($i=1; $i<=12; $i++) {
                $date->setDate(2000, $i, 1);
                $multioptions[$i] = $dateFormatter->format($date);
            }

            $options['value_options'] = $multioptions;

            /*if (isset($this->options['value_options'])) {
                $this->setValueOptions($this->options['value_options']);
            }*/

            //var_dump($options); exit;
        }

        parent::setOptions($options);

        return $this;
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