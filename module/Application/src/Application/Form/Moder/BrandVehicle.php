<?php

namespace Application\Form\Moder;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

use Application\Model\DbTable;

use Autowp\ZFComponents\Filter\SingleSpaces;

class BrandVehicle extends Form implements InputFilterProviderInterface
{
    private $languages = [];

    private $brandId = null;

    private $vehicleId = null;

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
                    'maxlength' => DbTable\BrandItem::MAX_CATNAME,
                ]
            ],
            [
                'name'    => 'type',
                'type'    => 'Select',
                'options' => [
                    'label'   => 'Type',
                    'options' => [
                        DbTable\BrandItem::TYPE_DEFAULT => 'catalogue/stock-model',
                        DbTable\BrandItem::TYPE_TUNING  => 'catalogue/related',
                        DbTable\BrandItem::TYPE_SPORT   => 'catalogue/sport',
                        DbTable\BrandItem::TYPE_DESIGN  => 'catalogue/design',
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
                'type'    => BrandVehicleLanguage::class,
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

        if (isset($options['brandId'])) {
            $this->setBrandId($options['brandId']);
            unset($options['brandId']);
        }

        if (isset($options['vehicleId'])) {
            $this->setVehicleId($options['vehicleId']);
            unset($options['vehicleId']);
        }

        parent::setOptions($options);

        return $this;
    }

    public function setBrandId($brandId)
    {
        $this->brandId = $brandId;

        return $this;
    }

    public function setVehicleId($vehicleId)
    {
        $this->vehicleId = $vehicleId;

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
                            'max' => DbTable\BrandItem::MAX_CATNAME
                        ]
                    ],
                    [
                        'name' => \Application\Validator\Brand\VehicleCatnameNotExists::class,
                        'options' => [
                            'brandId'         => $this->brandId,
                            'ignoreVehicleId' => $this->vehicleId
                        ]
                    ]
                ]
            ],
        ];
    }
}
