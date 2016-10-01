<?php

namespace Application\Form\Moder;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

use Application\Filter\SingleSpaces;
use Application\Model\DbTable\Page;

class Page extends Form implements InputFilterProviderInterface
{
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
                    'label'     => 'page/parent',
                    'options'   => array_replace(['' => ''], $this->parents)
                ]
            ],
            [
                'name'    => 'name',
                'type'    => 'Text',
                'options' => [
                    'label'     => 'page/name',
                    'maxlength' => 255,
                    'size'      => 80
                ]
            ],
            [
                'name'    => 'title',
                'type'    => 'Text',
                'options' => [
                    'label'     => 'Title',
                    'maxlength' => 255,
                    'size'      => 80
                ]
            ],
            [
                'name'    => 'breadcrumbs',
                'type'    => 'Text',
                'options' => [
                    'label'     => 'Breadcrumbs',
                    'maxlength' => 255,
                    'size'      => 80
                ]
            ],
            [
                'name'    => 'url',
                'type'    => 'Text',
                'options' => [
                    'label'     => 'URL',
                    'maxlength' => 255,
                    'size'      => 80
                ]
            ],
            [
                'name'    => 'is_group_node',
                'type'    => 'Checkbox',
                'options' => [
                    'label' => 'page/is_group_node'
                ]
            ],
            [
                'name'    => 'registered_only',
                'type'    => 'Checkbox',
                'options' => [
                    'label' => 'page/registered_only'
                ]
            ],
            [
                'name'    => 'guest_only',
                'type'    => 'Checkbox',
                'options' => [
                    'label' => 'page/guests_only'
                ]
            ],
            [
                'name'    => 'class',
                'type'    => 'Text',
                'options' => [
                    'label'     => 'page/class',
                    'maxlength' => 255,
                    'size'      => 80
                ]
            ]
        ];

        foreach ($elements as $element) {
            $this->add($element);
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
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => Page::MAX_NAME
                        ]
                    ]
                ]
            ],
            'title' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => Page::MAX_TITLE
                        ]
                    ]
                ]
            ],
            'breadcrumbs' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => Page::MAX_BREADCRUMBS
                        ]
                    ]
                ]
            ],
            'url' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => Page::MAX_URL
                        ]
                    ]
                ]
            ],
            'is_group_node' => [
                'required' => false
            ],
            'registered_only' => [
                'required' => false
            ],
            'guest_only' => [
                'required' => false
            ],
            'class' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => Page::MAX_CLASS
                        ]
                    ]
                ]
            ],
        ];
    }
}