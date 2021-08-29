<?php

namespace Application\Form\Element;

use DateTime;
use Exception;
use IntlDateFormatter;
use Laminas\Form\Element\Select;
use Laminas\Form\Exception\InvalidArgumentException;
use Laminas\InputFilter\InputProviderInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function is_array;

class Month extends Select implements InputProviderInterface
{
    /** @var array<string, string> */
    protected $attributes = [
        'type' => 'select',
    ];

    /** @var null|string */
    protected $label = 'month';

    private string $language = 'en';

    /**
     * @param array|Traversable $options
     * @throws Exception
     */
    public function setOptions($options): Month
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }

        if (isset($options['language'])) {
            $this->language = $options['language'];
        }

        if (! isset($options['options']) && ! isset($options['value_options'])) {
            $multioptions = [
                '' => '--',
            ];

            $dateFormatter = new IntlDateFormatter(
                $this->language,
                IntlDateFormatter::LONG,
                IntlDateFormatter::NONE,
                null,
                null,
                'MM - MMMM'
            );

            $date = new DateTime();
            for ($i = 1; $i <= 12; $i++) {
                $date->setDate(2000, $i, 1);
                $multioptions[$i] = $dateFormatter->format($date);
            }

            $options['value_options'] = $multioptions;

            /*if (isset($this->options['value_options'])) {
                $this->setValueOptions($this->options['value_options']);
            }*/
        }

        parent::setOptions($options);

        return $this;
    }

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
                        'min'       => 1,
                        'max'       => 12,
                        'inclusive' => true,
                    ],
                ],
            ],
        ];
    }
}
