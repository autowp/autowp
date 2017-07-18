<?php

namespace Application\Form\Moder;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

use Autowp\ZFComponents\Filter\SingleSpaces;

class ItemParent extends Form implements InputFilterProviderInterface
{
    private $languages = [];

    private $parentId = null;

    private $itemId = null;

    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);

        $this->setWrapElements(true);

        $elements = [
            [
                'name'    => 'catname',
                'type'    => 'Text',
                'options' => [
                    'label'     => 'Catname',
                    'maxlength' => \Application\Model\ItemParent::MAX_CATNAME
                ]
            ],
            [
                'name'    => 'type',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'Type',
                    'options' => [
                        \Application\Model\ItemParent::TYPE_DEFAULT => 'catalogue/stock-model',
                        \Application\Model\ItemParent::TYPE_TUNING  => 'catalogue/related',
                        \Application\Model\ItemParent::TYPE_SPORT   => 'catalogue/sport',
                        \Application\Model\ItemParent::TYPE_DESIGN  => 'catalogue/design',
                    ],
                ]
            ]
        ];

        foreach ($elements as $element) {
            $this->add($element);
        }

        foreach ($this->languages as $language) {
            $this->add([
                'name'    => $language,
                'type'    => ItemParentLanguage::class,
                'options' => [
                    'label' => $language
                ]
            ]);
            $this->get($language)->setWrapElements(true)->prepare();
        }

        $this->setAttribute('method', 'post');
    }

    /**
     * Set options for a fieldset. Accepted options are:
     * - use_as_base_fieldset: is this fieldset use as the base fieldset?
     *
     * @param  array|Traversable $options
     * @return Element|ElementInterface
     * @throws Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        if (isset($options['languages'])) {
            $this->languages = $options['languages'];
            unset($options['languages']);
        }

        if (isset($options['parentId'])) {
            $this->setParentId($options['parentId']);
            unset($options['parentId']);
        }

        if (isset($options['itemId'])) {
            $this->setItemId($options['itemId']);
            unset($options['itemId']);
        }

        parent::setOptions($options);

        return $this;
    }

    public function setParentId($parentId)
    {
        $this->parentId = $parentId;

        return $this;
    }

    public function setItemId($itemId)
    {
        $this->itemId = $itemId;

        return $this;
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
            'catname' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class],
                    ['name' => \Autowp\ZFComponents\Filter\FilenameSafe::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => 0,
                            'max' => \Application\Model\ItemParent::MAX_CATNAME
                        ]
                    ],
                    [
                        'name' => \Application\Validator\ItemParent\CatnameNotExists::class,
                        'options' => [
                            'parentId'     => $this->parentId,
                            'ignoreItemId' => $this->itemId
                        ]
                    ]
                ]
            ],
        ];
    }
}
