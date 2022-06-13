<?php

namespace Application\Form\Moder;

use Application\Model\ItemParent as ItemParentModel;
use Autowp\ZFComponents\Filter\SingleSpaces;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;

class ItemParentLanguage extends Form implements InputFilterProviderInterface
{
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
     * {@link \Laminas\InputFilter\Factory::createInputFilter()}.
     */
    public function getInputFilterSpecification(): array // @phpstan-ignore-line
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
