<?php

namespace Application\Form\Moder;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

use Car_Types;
use Picture;

class Inbox extends Form implements InputFilterProviderInterface
{
    private $perspectiveOptions = [];

    private $brandOptions = [];

    /**
     * @var Car_Types
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
                        Picture::STATUS_INBOX    => 'moder/picture/filter/status/inbox',
                        Picture::STATUS_NEW      => 'moder/picture/filter/status/new',
                        Picture::STATUS_ACCEPTED => 'moder/picture/filter/status/accepted',
                        Picture::STATUS_REMOVING => 'moder/picture/filter/status/removing',
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
                    'label'   => 'moder/picture/filter/perspective',
                    'options' => $this->perspectiveOptions
                ]
            ],
            [
                'name'    => 'brand_id',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'moder/picture/filter/brand',
                    'options' => $this->brandOptions
                ]
            ],
            [
                'name'    => 'car_id',
                'type'    => 'Text',
                'options' => [
                    'label' => 'moder/picture/filter/vehicle',
                ]
            ],
            [
                'name'    => 'type_id',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'moder/picture/filter/type',
                    'options' => [
                        ''                        => 'любой',
                        Picture::CAR_TYPE_ID      => 'автомобиль',
                        Picture::LOGO_TYPE_ID     => 'логотип',
                        Picture::MIXED_TYPE_ID    => 'разное',
                        Picture::UNSORTED_TYPE_ID => 'несортировано',
                        Picture::ENGINE_TYPE_ID   => 'двигатель',
                        Picture::FACTORY_TYPE_ID  => 'завод'
                    ]
                ]
            ],
            [
                'name'    => 'comments',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'moder/picture/filter/comments',
                    'options' => [
                        ''  => 'не важно',
                        '1' => 'есть',
                        '0' => 'нет',
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
                    'label'   => 'moder/picture/filter/replace',
                    'options' => [
                        ''  => 'не важно',
                        '1' => 'замена',
                        '0' => 'кроме замен',
                    ],
                ]
            ],
            [
                'name'    => 'requests',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'moder/picture/filter/votes',
                    'options' => [
                        ''  => 'не важно',
                        '0' => 'нет',
                        '1' => 'есть на принятие',
                        '2' => 'есть на удаление',
                        '3' => 'есть любые',
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
                'name'    => 'order',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'moder/picture/filter/order',
                    'options' => [
                        1 => 'Дата добавления (новые)',
                        2 => 'Дата добавления (старые)',
                        3 => 'Разрешение (большие)',
                        4 => 'Разрешение (маленькие)',
                        5 => 'Размер (большие)',
                        6 => 'Размер (маленькие)',
                        7 => 'Комментируемые',
                        8 => 'Просмотры',
                        9 => 'Заявки на принятие/удаление'
                    ],
                    'value'        => '1',
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
     * @return Car_Types
     */
    private function getCarTypeTable()
    {
        return $this->carTypeTable
            ? $this->carTypeTable
            : $this->carTypeTable = new Car_Types();
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
            'brand_id' => [
                'required' => false
            ],
            'car_id' => [
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
            'order' => [
                'required' => false
            ],
        ];
    }
}