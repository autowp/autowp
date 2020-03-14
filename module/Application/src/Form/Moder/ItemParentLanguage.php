<?php

namespace Application\Form\Moder;

use Application\Model\ItemParent as ItemParentModel;
use Autowp\ZFComponents\Filter\SingleSpaces;
use Laminas\Form\Element;
use Laminas\Form\ElementInterface;
use Laminas\Form\Exception;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Traversable;

class ItemParentLanguage extends Form implements InputFilterProviderInterface
{
    private string $language = 'en';

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

    public function __construct(?string $name = null, array $options = [])
    {
        parent::__construct($name, $options);

        $elements = [
            [
                'name'       => 'name',
                'type'       => 'Text',
                'options'    => [
                    'label' => 'Name',
                ],
                'attributes' => [
                    'maxlength' => ItemParentModel::MAX_LANGUAGE_NAME,
                ],
            ],
        ];

        foreach ($elements as $element) {
            $this->add($element);
        }
    }

    /**
     * Should return an array specification compatible with
     * {@link Laminas\InputFilter\Factory::createInputFilter()}.
     */
    public function getInputFilterSpecification(): array
    {
        return [
            'name' => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class],
                ],
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'min' => 0,
                            'max' => ItemParentModel::MAX_LANGUAGE_NAME,
                        ],
                    ],
                ],
            ],
        ];
    }
}
