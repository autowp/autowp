<?php

namespace Application\Form\Moder;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

use Autowp\ZFComponents\Filter\SingleSpaces;

class Category extends Form implements InputFilterProviderInterface
{
    private $languages = [];

    private $parents = [];

    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);

        $this->setWrapElements(true);

        $elements = [
            [
                'name'    => 'parent_id',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'category/parent',
                    'options' => array_replace(['' => ''], $this->parents)
                ]
            ],
            [
                'name'    => 'name',
                'type'    => 'Text',
                'options' => [
                    'label'     => 'category/name',
                    'maxlength' => 255,
                    'size'      => 80
                ]
            ],
            [
                'name'    => 'short_name',
                'type'    => 'Text',
                'options' => [
                    'label'     => 'Short name',
                    'maxlength' => 255,
                    'size'      => 80
                ]
            ],
            [
                'name'    => 'catname',
                'type'    => 'Text',
                'options' => [
                    'label'     => 'Cat name',
                    'maxlength' => 255,
                    'size'      => 80
                ]
            ]
        ];

        foreach ($elements as $element) {
            $this->add($element);
        }

        foreach ($this->languages as $language) {
            $this->add([
                'name'    => $language,
                'type'    => CategoryLanguage::class,
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

        if (isset($options['parents'])) {
            $this->parents = $options['parents'];
            unset($options['parents']);
        }

        parent::setOptions($options);

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
            'parent_id' => [
                'required' => false
            ],
            'name' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ]
            ],
            'short_name' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ]
            ],
            'catname' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ]
            ]
        ];
    }
}
