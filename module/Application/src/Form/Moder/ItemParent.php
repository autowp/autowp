<?php

namespace Application\Form\Moder;

use Application\Model\ItemParent as ItemParentModel;
use Application\Validator\ItemParent\CatnameNotExists;
use Autowp\ZFComponents\Filter\FilenameSafe;
use Autowp\ZFComponents\Filter\SingleSpaces;
use Laminas\Form\Element;
use Laminas\Form\ElementInterface;
use Laminas\Form\Exception;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Traversable;

class ItemParent extends Form implements InputFilterProviderInterface
{
    private array $languages = [];

    private int $parentId;

    private int $itemId;

    public function __construct(?string $name = null, array $options = [])
    {
        parent::__construct($name, $options);

        $this->setWrapElements(true);

        $elements = [
            [
                'name'    => 'catname',
                'type'    => 'Text',
                'options' => [
                    'label'     => 'Catname',
                    'maxlength' => ItemParentModel::MAX_CATNAME,
                ],
            ],
            [
                'name'    => 'type',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'Type',
                    'options' => [
                        ItemParentModel::TYPE_DEFAULT => 'catalogue/stock-model',
                        ItemParentModel::TYPE_TUNING  => 'catalogue/related',
                        ItemParentModel::TYPE_SPORT   => 'catalogue/sport',
                        ItemParentModel::TYPE_DESIGN  => 'catalogue/design',
                    ],
                ],
            ],
        ];

        foreach ($elements as $element) {
            $this->add($element);
        }

        foreach ($this->languages as $language) {
            $this->add([
                'name'    => $language,
                'type'    => ItemParentLanguage::class,
                'options' => [
                    'label' => $language,
                ],
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

    public function setParentId(int $parentId): self
    {
        $this->parentId = $parentId;

        return $this;
    }

    public function setItemId(int $itemId): self
    {
        $this->itemId = $itemId;

        return $this;
    }

    /**
     * Should return an array specification compatible with
     * {@link Laminas\InputFilter\Factory::createInputFilter()}.
     */
    public function getInputFilterSpecification(): array
    {
        return [
            'catname' => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class],
                    ['name' => FilenameSafe::class],
                ],
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'min' => 0,
                            'max' => ItemParentModel::MAX_CATNAME,
                        ],
                    ],
                    [
                        'name'    => CatnameNotExists::class,
                        'options' => [
                            'parentId'     => $this->parentId,
                            'ignoreItemId' => $this->itemId,
                        ],
                    ],
                ],
            ],
        ];
    }
}
