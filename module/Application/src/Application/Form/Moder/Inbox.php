<?php

namespace Application\Form\Moder;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

use Application\Model\DbTable;

class Inbox extends Form implements InputFilterProviderInterface
{
    private $perspectiveOptions = [];

    private $brandOptions = [];

    /**
     * @var DbTable\Vehicle\Type
     */
    private $carTypeTable = null;

    public function setPerspectiveOptions($options)
    {
        $this->perspectiveOptions = $options;
    }

    public function setBrandOptions($options)
    {
        $this->brandOptions = $options;
    }

    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);

        $carTypeOptions = $this->getCarTypeOptions();

        $carTypeOptions = ['' => '-'] + $carTypeOptions;

        $elements = [
            [
                'name'    => 'status',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'moder/picture/filter/status',
                    'options' => [
                        ''                       => 'moder/picture/filter/status/any',
                        DbTable\Picture::STATUS_INBOX    => 'moder/picture/filter/status/inbox',
                        DbTable\Picture::STATUS_NEW      => 'moder/picture/filter/status/new',
                        DbTable\Picture::STATUS_ACCEPTED => 'moder/picture/filter/status/accepted',
                        DbTable\Picture::STATUS_REMOVING => 'moder/picture/filter/status/removing',
                        'custom1'                => 'moder/picture/filter/status/all-except-removing'
                    ],
                ]
            ],
            [
                'name'    => 'car_type_id',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'moder/picture/filter/vehicle-type',
                    'options' => $carTypeOptions,
                ]
            ],
            [
                'name'    => 'perspective_id',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'moder/pictures/filter/perspective',
                    'options' => $this->perspectiveOptions
                ]
            ],
            [
                'name'    => 'item_id',
                'type'    => 'Text',
                'options' => [
                    'label' => 'moder/picture/filter/item',
                ]
            ],
            [
                'name'    => 'comments',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'moder/pictures/filter/comments',
                    'options' => [
                        ''  => 'moder/pictures/filter/comments/not-matters',
                        '1' => 'moder/pictures/filter/comments/has-comments',
                        '0' => 'moder/pictures/filter/comments/has-no-comments',
                    ]
                ]
            ],
            [
                'name'    => 'owner_id',
                'type'    => 'Text',
                'options' => [
                    'label' => 'moder/picture/filter/owner'
                ]
            ],
            [
                'name'    => 'replace',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'moder/pictures/filter/replace',
                    'options' => [
                        ''  => 'moder/pictures/filter/replace/not-matters',
                        '1' => 'moder/pictures/filter/replace/replaces',
                        '0' => 'moder/pictures/filter/replace/without-replaces'
                    ],
                ]
            ],
            [
                'name'    => 'requests',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'moder/pictures/filter/votes',
                    'options' => [
                        ''  => 'moder/pictures/filter/votes/not-matters',
                        '0' => 'moder/pictures/filter/votes/none',
                        '1' => 'moder/pictures/filter/votes/accept',
                        '2' => 'moder/pictures/filter/votes/accept',
                        '3' => 'moder/pictures/filter/votes/any',
                    ],
                ]
            ],
            [
                'name'    => 'special_name',
                'type'    => 'Checkbox',
                'options' => [
                    'label' => 'moder/picture/filter/special-name',
                    'value' => '1',
                ]
            ],
            [
                'name'    => 'lost',
                'type'    => 'Checkbox',
                'options' => [
                    'label' => 'moder/picture/filter/not-linked',
                    'value' => '1',
                ]
            ],
            [
                'name'    => 'gps',
                'type'    => 'Checkbox',
                'options' => [
                    'label' => 'moder/picture/filter/gps',
                    'value' => '1',
                ]
            ],
            [
                'name'    => 'similar',
                'type'    => 'Checkbox',
                'options' => [
                    'label' => 'moder/picture/filter/similar',
                    'value' => '1',
                ]
            ],
            [
                'name'    => 'order',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'moder/picture/filter/order',
                    'options' => [
                        1 => 'moder/pictures/filter/order/add-date-desc',
                        2 => 'moder/pictures/filter/order/add-date-asc',
                        3 => 'moder/pictures/filter/order/resolution-desc',
                        4 => 'moder/pictures/filter/order/resolution-asc',
                        5 => 'moder/pictures/filter/order/filesize-desc',
                        6 => 'moder/pictures/filter/order/filesize-asc',
                        7 => 'moder/pictures/filter/order/commented',
                        8 => 'moder/pictures/filter/order/views',
                        9 => 'moder/pictures/filter/order/moder-votes'
                    ],
                    'value' => '1',
                ]
            ],
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
        if (isset($options['perspectiveOptions'])) {
            $this->perspectiveOptions = $options['perspectiveOptions'];
            unset($options['perspectiveOptions']);
        }

        if (isset($options['brandOptions'])) {
            $this->brandOptions = $options['brandOptions'];
            unset($options['brandOptions']);
        }

        parent::setOptions($options);

        return $this;
    }

    /**
     * @return DbTable\Vehicle\Type
     */
    private function getCarTypeTable()
    {
        return $this->carTypeTable
            ? $this->carTypeTable
            : $this->carTypeTable = new DbTable\Vehicle\Type();
    }

    private function getCarTypeOptions($parentId = null)
    {
        if ($parentId) {
            $filter = [
                'parent_id = ?' => $parentId
            ];
        } else {
            $filter = 'parent_id is null';
        }

        $rows = $this->getCarTypeTable()->fetchAll($filter, 'position');
        $result = [];
        foreach ($rows as $row) {
            $result[$row->id] = $row->name;

            foreach ($this->getCarTypeOptions($row->id) as $key => $value) {
                $result[$key] = '...' . $value; //$translate->translate($value);
            }
        }

        return $result;
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
            'status' => [
                'required' => false
            ],
            'car_type_id' => [
                'required' => false
            ],
            'perspective_id' => [
                'required' => false
            ],
            'item_id' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'type_id' => [
                'required' => false
            ],
            'comments' => [
                'required' => false
            ],
            'owner_id' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'replace' => [
                'required' => false
            ],
            'requests' => [
                'required' => false
            ],
            'special_name' => [
                'required' => false
            ],
            'lost' => [
                'required' => false
            ],
            'gps' => [
                'required' => false
            ],
            'order' => [
                'required' => false
            ],
            'similar' => [
                'required' => false
            ]
        ];
    }
}
