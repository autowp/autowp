<?php

namespace Application\Form\Element;

use Laminas\Form\Element\Number;
use Laminas\InputFilter\InputProviderInterface;

use function date;

class Year extends Number implements InputProviderInterface
{
    /** @var array<string, string> */
    protected $attributes = [
        'type'      => 'number',
        'maxlength' => '4',
        'size'      => '4',
    ];

    /** @var null|string */
    protected $label = 'year';

    /**
     * Provide default input rules for this element
     *
     * Attaches a phone number validator.
     */
    public function getInputSpecification(): array
    {
        return [
            'name'       => $this->getName(),
            'required'   => true,
            'filters'    => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                ['name' => 'Digits'],
                [
                    'name'    => 'Between',
                    'options' => [
                        'min'       => 1700,
                        'max'       => date('Y') + 3,
                        'inclusive' => true,
                    ],
                ],
            ],
        ];
    }
}
